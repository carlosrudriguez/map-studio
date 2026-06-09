/**
 * Animates SVG viewBox changes without rasterizing the vector map.
 * The frontend map script depends on this helper for smooth zoom transitions.
 */

window.MapStudioViewBoxAnimation = window.MapStudioViewBoxAnimation || {};

window.MapStudioViewBoxAnimation.create = (svgElement, options = {}) => {
  const duration = Number.isFinite(options.duration) ? options.duration : 650;
  const reducedMotionQuery = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : null;
  let animationFrame = 0;

  const formatViewBox = (viewBox) => [viewBox.x, viewBox.y, viewBox.width, viewBox.height]
    .map((value) => Number(value.toFixed(3)))
    .join(' ');

  const parseViewBox = () => {
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

  const interpolate = (start, end, progress) => ({
    x: start.x + (end.x - start.x) * progress,
    y: start.y + (end.y - start.y) * progress,
    width: start.width + (end.width - start.width) * progress,
    height: start.height + (end.height - start.height) * progress,
  });

  const ease = (progress) => 1 - ((1 - progress) ** 3);

  const cancel = () => {
    if (animationFrame) {
      window.cancelAnimationFrame(animationFrame);
      animationFrame = 0;
    }
  };

  const set = (targetViewBox, onUpdate = null) => {
    cancel();

    if (reducedMotionQuery && reducedMotionQuery.matches) {
      svgElement.setAttribute('viewBox', formatViewBox(targetViewBox));

      if (typeof onUpdate === 'function') {
        onUpdate();
      }

      return;
    }

    const startViewBox = parseViewBox();
    let startTime = null;

    const step = (now) => {
      if (startTime === null) {
        startTime = now;
      }

      const elapsed = Math.max(0, now - startTime);
      const progress = Math.min(elapsed / duration, 1);
      svgElement.setAttribute('viewBox', formatViewBox(interpolate(startViewBox, targetViewBox, ease(progress))));

      if (typeof onUpdate === 'function') {
        onUpdate();
      }

      if (progress < 1) {
        animationFrame = window.requestAnimationFrame(step);
        return;
      }

      animationFrame = 0;
    };

    animationFrame = window.requestAnimationFrame(step);
  };

  return { cancel, current: parseViewBox, set };
};
