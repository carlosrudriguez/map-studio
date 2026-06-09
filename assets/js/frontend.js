/**
 * Runs public interactions for each Map Studio instance.
 * It opens sanitized region content in an anchored, scrollable bubble.
 */

window.MapStudio = window.MapStudio || {};

window.MapStudio.init = (mapElement) => {
  if (!mapElement || mapElement.dataset.mapStudioReady === 'true') {
    return;
  }

  const dataElement = mapElement.querySelector('.map-studio__data');
  const bubble = mapElement.querySelector('.map-studio__bubble');
  const bubbleContent = mapElement.querySelector('.map-studio__bubble-content');
  const closeButton = mapElement.querySelector('.map-studio__close');
  const resetButton = mapElement.querySelector('.map-studio__reset');
  const svgElement = mapElement.querySelector('.map-studio__svg');
  const viewport = mapElement.querySelector('.map-studio__viewport') || mapElement;

  if (!dataElement || !bubble || !bubbleContent || !closeButton || !resetButton || !svgElement) {
    return;
  }

  let contentByRegion = {};
  let selectedRegionElement = null;
  let currentZoomScale = 1;

  try {
    const parsed = JSON.parse(dataElement.textContent || '{}');
    contentByRegion = parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : {};
  } catch (error) {
    contentByRegion = {};
  }

  const activeRegionElements = Array.from(mapElement.querySelectorAll('.map-studio__region.is-active'));
  const regionListButtons = Array.from(mapElement.querySelectorAll('.map-studio__region-list-button'));
  const zoomSettings = { minScale: 1.6, maxScale: 3.4, viewportRatio: 0.44 };

  const clamp = (value, min, max) => Math.min(Math.max(value, min), Math.max(min, max));
  const regionKeyFor = (regionElement) => regionElement.getAttribute('data-map-studio-region-key') || '';

  const contentFor = (regionElement) => contentByRegion[regionKeyFor(regionElement)] || '';

  const isMapActive = () => bubble.classList.contains('is-open') || mapElement.classList.contains('is-zoomed');

  const blurActiveControl = () => {
    const activeElement = document.activeElement;
    const activeControlHasFocus = activeElement && typeof activeElement.blur === 'function' && (
      closeButton.contains(activeElement) ||
      resetButton.contains(activeElement) ||
      regionListButtons.some((button) => button.contains(activeElement))
    );

    if (activeControlHasFocus) {
      activeElement.blur();
    }
  };

  const svgViewBox = () => {
    const values = (svgElement.getAttribute('viewBox') || '')
      .trim()
      .split(/[\s,]+/)
      .map((value) => Number(value));

    if (values.length === 4 && values.every((value) => Number.isFinite(value)) && values[2] > 0 && values[3] > 0) {
      return { x: values[0], y: values[1], width: values[2], height: values[3] };
    }

    return {
      x: 0,
      y: 0,
      width: parseFloat(svgElement.getAttribute('width') || '') || 1,
      height: parseFloat(svgElement.getAttribute('height') || '') || 1,
    };
  };

  const svgDisplaySize = () => {
    const rect = svgElement.getBoundingClientRect();

    return { width: svgElement.clientWidth || rect.width / currentZoomScale || 1, height: svgElement.clientHeight || rect.height / currentZoomScale || 1 };
  };

  const zoomTargetFor = (regionElement) => {
    if (typeof regionElement.getBBox !== 'function') {
      return null;
    }

    let box = null;

    try {
      box = regionElement.getBBox();
    } catch (error) {
      return null;
    }

    if (!box || !Number.isFinite(box.width) || !Number.isFinite(box.height)) {
      return null;
    }

    const viewBox = svgViewBox();
    const size = svgDisplaySize();
    const mapWidth = viewport.clientWidth || size.width;
    const mapHeight = size.height;
    const unitX = size.width / viewBox.width;
    const unitY = size.height / viewBox.height;
    const regionWidth = Math.max(box.width * unitX, 1);
    const regionHeight = Math.max(box.height * unitY, 1);
    const fitScale = Math.min(
      (size.width * zoomSettings.viewportRatio) / regionWidth,
      (size.height * zoomSettings.viewportRatio) / regionHeight
    );
    const scale = clamp(fitScale, zoomSettings.minScale, zoomSettings.maxScale);
    const targetX = (box.x + box.width / 2 - viewBox.x) * unitX;
    const targetY = (box.y + box.height / 2 - viewBox.y) * unitY;
    const minX = Math.min(0, mapWidth - size.width * scale);
    const minY = Math.min(0, mapHeight - size.height * scale);
    const x = clamp(mapWidth / 2 - targetX * scale, minX, 0);
    const y = clamp(mapHeight / 2 - targetY * scale, minY, 0);

    return { scale, x, y, center: { x: targetX * scale + x, y: targetY * scale + y } };
  };

  const setMapZoom = (scale, x, y) => {
    currentZoomScale = scale;
    mapElement.classList.toggle('is-zoomed', scale > 1);
    resetButton.hidden = scale <= 1;

    if (scale <= 1) {
      mapElement.style.removeProperty('--map-studio-zoom-scale');
      mapElement.style.removeProperty('--map-studio-zoom-x');
      mapElement.style.removeProperty('--map-studio-zoom-y');
      return;
    }

    mapElement.style.setProperty('--map-studio-zoom-scale', String(scale));
    mapElement.style.setProperty('--map-studio-zoom-x', `${x}px`);
    mapElement.style.setProperty('--map-studio-zoom-y', `${y}px`);
  };

  const resetZoom = () => setMapZoom(1, 0, 0);

  const zoomToRegion = (regionElement) => {
    const target = zoomTargetFor(regionElement);

    if (!target) {
      resetZoom();
      return null;
    }

    setMapZoom(target.scale, target.x, target.y);
    return target;
  };

  const fallbackCenterFor = (regionElement) => {
    const mapRect = viewport.getBoundingClientRect();
    const regionRect = regionElement.getBoundingClientRect();

    return { x: regionRect.left + regionRect.width / 2 - mapRect.left, y: regionRect.top + regionRect.height / 2 - mapRect.top };
  };

  const transformPointToMap = (point, matrix) => {
    const mapRect = viewport.getBoundingClientRect();
    const screenPoint = point.matrixTransform(matrix);

    return { x: screenPoint.x - mapRect.left, y: screenPoint.y - mapRect.top };
  };

  const sampledPathCenterFor = (regionElement, matrix) => {
    const lacksPathSampling = typeof regionElement.getTotalLength !== 'function' ||
      typeof regionElement.getPointAtLength !== 'function' ||
      typeof svgElement.createSVGPoint !== 'function';

    if (lacksPathSampling) {
      return null;
    }

    const length = regionElement.getTotalLength();

    if (!Number.isFinite(length) || length <= 0) {
      return null;
    }

    const sampleCount = 32;
    const sampledPoint = svgElement.createSVGPoint();
    let x = 0;
    let y = 0;

    for (let index = 0; index < sampleCount; index += 1) {
      const point = regionElement.getPointAtLength((length * (index + 0.5)) / sampleCount);
      x += point.x;
      y += point.y;
    }

    sampledPoint.x = x / sampleCount;
    sampledPoint.y = y / sampleCount;

    return transformPointToMap(sampledPoint, matrix);
  };

  const centerFor = (regionElement) => {
    const lacksSvgGeometry = typeof svgElement.createSVGPoint !== 'function' ||
      typeof regionElement.getBBox !== 'function' ||
      typeof regionElement.getScreenCTM !== 'function';

    if (lacksSvgGeometry) {
      return fallbackCenterFor(regionElement);
    }

    const matrix = regionElement.getScreenCTM();

    if (!matrix) {
      return fallbackCenterFor(regionElement);
    }

    try {
      const sampledCenter = sampledPathCenterFor(regionElement, matrix);

      if (sampledCenter) {
        return sampledCenter;
      }

      const box = regionElement.getBBox();
      const point = svgElement.createSVGPoint();

      point.x = box.x + box.width / 2;
      point.y = box.y + box.height / 2;

      return transformPointToMap(point, matrix);
    } catch (error) {
      return fallbackCenterFor(regionElement);
    }
  };

  const positionBubble = (regionElement, centerOverride = null) => {
    const mapRect = viewport.getBoundingClientRect();
    const center = centerOverride || centerFor(regionElement);
    const padding = 12;
    const gap = 10;
    const bubbleWidth = bubble.offsetWidth;
    const bubbleHeight = bubble.offsetHeight;
    const maxLeft = mapRect.width - bubbleWidth - padding;
    const maxTop = mapRect.height - bubbleHeight - padding;
    let left = center.x - bubbleWidth / 2;
    let top = center.y - bubbleHeight - gap;

    if (top < padding) {
      top = center.y + gap;
    }

    left = clamp(left, padding, maxLeft);
    top = clamp(top, padding, maxTop);

    bubble.style.setProperty('--map-studio-bubble-x', `${left}px`);
    bubble.style.setProperty('--map-studio-bubble-y', `${top}px`);
  };

  const setSelectedListButton = (regionKey) => {
    regionListButtons.forEach((button) => {
      const isSelected = (button.getAttribute('data-map-studio-region-key') || '') === regionKey;

      button.classList.toggle('is-selected', isSelected);
      button.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
    });
  };

  const resetMap = () => {
    if (selectedRegionElement) {
      selectedRegionElement.classList.remove('is-selected');
      selectedRegionElement = null;
    }

    setSelectedListButton('');
    bubble.classList.remove('is-open');
    bubble.setAttribute('aria-hidden', 'true');
    bubbleContent.innerHTML = '';
    resetZoom();
    blurActiveControl();
  };

  const openRegion = (regionElement) => {
    const content = contentFor(regionElement);

    if (!content) {
      resetMap();
      return;
    }

    if (selectedRegionElement && selectedRegionElement !== regionElement) {
      selectedRegionElement.classList.remove('is-selected');
    }

    selectedRegionElement = regionElement;
    selectedRegionElement.classList.add('is-selected');
    setSelectedListButton(regionKeyFor(regionElement));
    bubbleContent.innerHTML = content;
    bubble.classList.add('is-open');
    bubble.setAttribute('aria-hidden', 'false');
    const zoomTarget = zoomToRegion(regionElement);

    window.requestAnimationFrame(() => {
      positionBubble(regionElement, zoomTarget ? zoomTarget.center : null);
    });
  };

  activeRegionElements.forEach((regionElement) => {
    regionElement.addEventListener('click', (event) => {
      event.stopPropagation();
      openRegion(regionElement);
    });

    regionElement.addEventListener('keydown', (event) => {
      if (event.key !== 'Enter' && event.key !== ' ') {
        return;
      }

      event.preventDefault();
      openRegion(regionElement);
    });
  });

  regionListButtons.forEach((button) => {
    button.addEventListener('click', (event) => {
      event.stopPropagation();

      const regionKey = button.getAttribute('data-map-studio-region-key') || '';
      const regionElement = activeRegionElements.find((activeRegionElement) => regionKeyFor(activeRegionElement) === regionKey);

      if (regionElement) {
        openRegion(regionElement);
      }
    });
  });

  closeButton.addEventListener('click', (event) => {
    event.stopPropagation();
    resetMap();
  });

  resetButton.addEventListener('click', (event) => {
    event.stopPropagation();
    resetMap();
  });

  mapElement.addEventListener('click', (event) => {
    const target = event.target;

    if (!(target instanceof Element)) {
      return;
    }

    if (bubble.contains(target) || resetButton.contains(target) || target.closest('.map-studio__region-list')) {
      return;
    }

    if (target.closest('.map-studio__region.is-active')) {
      return;
    }

    resetMap();
  });

  document.addEventListener('click', (event) => {
    if (!isMapActive()) {
      return;
    }

    const target = event.target;

    if (!(target instanceof Element)) {
      return;
    }

    if (mapElement.contains(target)) {
      return;
    }

    resetMap();
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && isMapActive()) {
      event.preventDefault();
      resetMap();
    }
  });

  window.addEventListener('resize', () => {
    if (selectedRegionElement && bubble.classList.contains('is-open')) {
      const zoomTarget = zoomToRegion(selectedRegionElement);
      positionBubble(selectedRegionElement, zoomTarget ? zoomTarget.center : null);
    }
  });

  mapElement.dataset.mapStudioReady = 'true';
};

document.addEventListener('DOMContentLoaded', () => document.querySelectorAll('.map-studio').forEach((mapElement) => window.MapStudio.init(mapElement)));
