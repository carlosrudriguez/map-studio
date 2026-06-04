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
  const svgElement = mapElement.querySelector('.map-studio__svg');

  if (!dataElement || !bubble || !bubbleContent || !closeButton || !svgElement) {
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

  const clamp = (value, min, max) => Math.min(Math.max(value, min), Math.max(min, max));

  const regionKeyFor = (regionElement) => regionElement.getAttribute('data-map-studio-region-key') || '';

  const contentFor = (regionElement) => contentByRegion[regionKeyFor(regionElement)] || '';

  const fallbackCenterFor = (regionElement) => {
    const mapRect = mapElement.getBoundingClientRect();
    const regionRect = regionElement.getBoundingClientRect();

    return {
      x: regionRect.left + regionRect.width / 2 - mapRect.left,
      y: regionRect.top + regionRect.height / 2 - mapRect.top,
    };
  };

  const transformPointToMap = (point, matrix) => {
    const mapRect = mapElement.getBoundingClientRect();
    const screenPoint = point.matrixTransform(matrix);

    return {
      x: screenPoint.x - mapRect.left,
      y: screenPoint.y - mapRect.top,
    };
  };

  const sampledPathCenterFor = (regionElement, matrix) => {
    if (
      typeof regionElement.getTotalLength !== 'function' ||
      typeof regionElement.getPointAtLength !== 'function' ||
      typeof svgElement.createSVGPoint !== 'function'
    ) {
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
    if (
      typeof svgElement.createSVGPoint !== 'function' ||
      typeof regionElement.getBBox !== 'function' ||
      typeof regionElement.getScreenCTM !== 'function'
    ) {
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
    const mapRect = mapElement.getBoundingClientRect();
    const center = centerFor(regionElement);
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

  const closeBubble = (restoreFocus = true) => {
    const regionToFocus = selectedRegionElement;

    if (selectedRegionElement) {
      selectedRegionElement.classList.remove('is-selected');
      selectedRegionElement = null;
    }

    bubble.classList.remove('is-open');
    bubble.setAttribute('aria-hidden', 'true');
    bubbleContent.innerHTML = '';

    if (restoreFocus && regionToFocus && typeof regionToFocus.focus === 'function') {
      regionToFocus.focus({ preventScroll: true });
    }
  };

  const openRegion = (regionElement) => {
    const content = contentFor(regionElement);

    if (!content) {
      return;
    }

    if (selectedRegionElement && selectedRegionElement !== regionElement) {
      selectedRegionElement.classList.remove('is-selected');
    }

    selectedRegionElement = regionElement;
    selectedRegionElement.classList.add('is-selected');
    bubbleContent.innerHTML = content;
    bubble.classList.add('is-open');
    bubble.setAttribute('aria-hidden', 'false');

    window.requestAnimationFrame(() => {
      positionBubble(regionElement);
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

  closeButton.addEventListener('click', (event) => {
    event.stopPropagation();
    closeBubble(true);
  });

  document.addEventListener('click', (event) => {
    if (!bubble.classList.contains('is-open')) {
      return;
    }

    const target = event.target;

    if (!(target instanceof Element)) {
      return;
    }

    if (bubble.contains(target) || activeRegionElements.some((regionElement) => regionElement.contains(target))) {
      return;
    }

    closeBubble(false);
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && bubble.classList.contains('is-open')) {
      closeBubble(true);
    }
  });

  window.addEventListener('resize', () => {
    if (selectedRegionElement && bubble.classList.contains('is-open')) {
      positionBubble(selectedRegionElement);
    }
  });

  mapElement.dataset.mapStudioReady = 'true';
};

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.map-studio').forEach((mapElement) => {
    window.MapStudio.init(mapElement);
  });
});
