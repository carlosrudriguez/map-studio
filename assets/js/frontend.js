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
  const legendToggle = mapElement.querySelector('.map-studio__legend-toggle');
  const legendContent = mapElement.querySelector('.map-studio__legend-content');
  const regionListToggle = mapElement.querySelector('.map-studio__region-list-toggle');
  const regionList = mapElement.querySelector('.map-studio__region-list');
  const svgElement = mapElement.querySelector('.map-studio__svg');
  const viewport = mapElement.querySelector('.map-studio__viewport') || mapElement;

  if (!dataElement || !bubble || !bubbleContent || !closeButton || !resetButton || !svgElement) {
    return;
  }

  if (!window.MapStudioViewBoxAnimation || typeof window.MapStudioViewBoxAnimation.create !== 'function') {
    return;
  }

  let contentByRegion = {};
  let selectedRegionElement = null;

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
      (legendToggle && legendToggle.contains(activeElement)) ||
      (regionListToggle && regionListToggle.contains(activeElement)) ||
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

  const originalViewBox = svgViewBox();
  const viewBoxAnimation = window.MapStudioViewBoxAnimation.create(svgElement, { duration: 650 });

  const targetViewBoxFor = (regionElement) => {
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

    const fitScale = Math.min(
      (originalViewBox.width * zoomSettings.viewportRatio) / Math.max(box.width, 1),
      (originalViewBox.height * zoomSettings.viewportRatio) / Math.max(box.height, 1)
    );
    const scale = clamp(fitScale, zoomSettings.minScale, zoomSettings.maxScale);
    const width = originalViewBox.width / scale;
    const height = originalViewBox.height / scale;
    const centerX = box.x + box.width / 2;
    const centerY = box.y + box.height / 2;
    const maxX = originalViewBox.x + originalViewBox.width - width;
    const maxY = originalViewBox.y + originalViewBox.height - height;

    return {
      x: clamp(centerX - width / 2, originalViewBox.x, maxX),
      y: clamp(centerY - height / 2, originalViewBox.y, maxY),
      width,
      height,
    };
  };

  const resetZoom = () => {
    viewBoxAnimation.set(originalViewBox);
    mapElement.classList.remove('is-zoomed');
    resetButton.hidden = true;
  };

  const zoomToRegion = (regionElement, onUpdate = null) => {
    const targetViewBox = targetViewBoxFor(regionElement);

    if (!targetViewBox) {
      resetZoom();
      return;
    }

    viewBoxAnimation.set(targetViewBox, onUpdate);
    mapElement.classList.add('is-zoomed');
    resetButton.hidden = false;
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

  const positionBubble = (regionElement) => {
    const mapRect = viewport.getBoundingClientRect();
    const center = centerFor(regionElement);
    const padding = 12;
    const gap = 10;
    const bubbleWidth = bubble.offsetWidth;
    const bubbleHeight = bubble.offsetHeight;
    const maxLeft = mapRect.width - bubbleWidth - padding;
    const maxTop = mapRect.height - bubbleHeight - padding;
    const pointerPadding = 18;
    let left = center.x - bubbleWidth / 2;
    let top = center.y - bubbleHeight - gap;

    if (top < padding) {
      top = center.y + gap;
    }

    left = clamp(left, padding, maxLeft);
    top = clamp(top, padding, maxTop);

    const pointerX = clamp(center.x - left, pointerPadding, bubbleWidth - pointerPadding);
    const bubbleIsBelowRegion = center.y < top + bubbleHeight / 2;

    bubble.classList.toggle('is-above-region', !bubbleIsBelowRegion);
    bubble.classList.toggle('is-below-region', bubbleIsBelowRegion);
    bubble.style.setProperty('--map-studio-bubble-x', `${left}px`);
    bubble.style.setProperty('--map-studio-bubble-y', `${top}px`);
    bubble.style.setProperty('--map-studio-bubble-pointer-x', `${pointerX}px`);
  };

  const setSelectedListButton = (regionKey) => {
    regionListButtons.forEach((button) => {
      const isSelected = (button.getAttribute('data-map-studio-region-key') || '') === regionKey;

      button.classList.toggle('is-selected', isSelected);
      button.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
    });
  };

  const setRegionListCollapsed = (isCollapsed) => {
    if (!regionList || !regionListToggle) {
      return;
    }

    const showLabel = regionListToggle.getAttribute('data-map-studio-show-label') || 'Show region list';
    const hideLabel = regionListToggle.getAttribute('data-map-studio-hide-label') || 'Hide region list';

    mapElement.classList.toggle('is-region-list-collapsed', isCollapsed);
    regionList.hidden = isCollapsed;
    regionListToggle.setAttribute('aria-expanded', isCollapsed ? 'false' : 'true');
    regionListToggle.setAttribute('aria-label', isCollapsed ? showLabel : hideLabel);
  };

  const resetMap = () => {
    if (selectedRegionElement) {
      selectedRegionElement.classList.remove('is-selected');
      selectedRegionElement = null;
    }

    setSelectedListButton('');
    bubble.classList.remove('is-open', 'is-legend', 'is-above-region', 'is-below-region');
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
    bubble.classList.remove('is-legend');
    bubble.classList.add('is-open');
    bubble.setAttribute('aria-hidden', 'false');
    zoomToRegion(regionElement, () => positionBubble(regionElement));
  };

  const openLegend = () => {
    const content = legendContent ? legendContent.innerHTML.trim() : '';

    if (!content) {
      return;
    }

    if (selectedRegionElement) {
      selectedRegionElement.classList.remove('is-selected');
      selectedRegionElement = null;
    }

    setSelectedListButton('');
    resetZoom();
    bubbleContent.innerHTML = content;
    bubble.classList.remove('is-above-region', 'is-below-region');
    bubble.classList.add('is-open', 'is-legend');
    bubble.setAttribute('aria-hidden', 'false');
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

  if (regionListToggle && regionList) {
    setRegionListCollapsed(mapElement.classList.contains('is-region-list-collapsed') || regionList.hidden);

    regionListToggle.addEventListener('click', (event) => {
      event.stopPropagation();
      setRegionListCollapsed(!mapElement.classList.contains('is-region-list-collapsed'));
    });
  }

  if (legendToggle && legendContent) {
    legendToggle.addEventListener('click', (event) => {
      event.stopPropagation();
      openLegend();
    });
  }

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

    if (bubble.contains(target) || resetButton.contains(target) || target.closest('.map-studio__actions') || target.closest('.map-studio__region-list')) {
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
      zoomToRegion(selectedRegionElement, () => positionBubble(selectedRegionElement));
    }
  });

  mapElement.dataset.mapStudioReady = 'true';
};

document.addEventListener('DOMContentLoaded', () => document.querySelectorAll('.map-studio').forEach((mapElement) => window.MapStudio.init(mapElement)));
