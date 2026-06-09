/**
 * Powers the Map Studio admin region editor.
 * It keeps content and custom shape colors synchronized with hidden JSON fields.
 */

(() => {
  const adminRoots = () => document.querySelectorAll('.map-studio-admin');

  const parseJsonText = (text, fallback = {}) => {
    try {
      const parsed = JSON.parse(text || '{}');
      return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : fallback;
    } catch (error) {
      return fallback;
    }
  };

  const parseJsonField = (field) => parseJsonText(field.value || '{}', {});

  const normalizeContent = (content) => (content || '').trim();

  const normalizeColor = (color) => {
    const value = (color || '').trim().toLowerCase();
    return /^#[0-9a-f]{6}$/.test(value) ? value : '';
  };

  const initAdmin = (root) => {
    const jsonField = root.querySelector('.map-studio-admin__region-json');
    const colorJsonField = root.querySelector('.map-studio-admin__region-colors-json');
    const mapsDataElement = root.querySelector('.map-studio-admin__maps-data');
    const summary = root.querySelector('[data-map-studio-summary]');
    const regionList = root.querySelector('.map-studio-admin__regions');
    const mapSelect = root.querySelector('.map-studio-admin__map-select');
    const mapField = root.querySelector('[name="map_studio_map_id"]');
    const colorPanel = root.querySelector('.map-studio-admin__region-color');
    const colorToggle = root.querySelector('#map_studio_region_color_enabled');
    const colorInput = root.querySelector('#map_studio_region_color');
    const form = root.closest('form');
    const editorId = root.dataset.mapStudioEditorId || 'map_studio_editor';
    const textarea = document.getElementById(editorId);
    const isLocked = root.dataset.mapStudioMapLocked === 'true';
    const defaultRegionColor = normalizeColor(root.dataset.mapStudioDefaultRegionColor) || '#374151';

    if (!jsonField || !mapsDataElement || !summary || !regionList || !mapField || !textarea) {
      return;
    }

    const mapsData = parseJsonText(mapsDataElement.textContent || '{}', {});
    let contentByRegion = parseJsonField(jsonField);
    let colorByRegion = colorJsonField ? parseJsonField(colorJsonField) : {};
    let selectedMapId = mapField.value || '';
    let selectedRegionKey = root.dataset.mapStudioInitialRegionKey || '';
    let buttons = [];

    const shapesForSelectedMap = () => {
      const mapData = mapsData[selectedMapId] || {};
      return Array.isArray(mapData.shapes) ? mapData.shapes : [];
    };

    const getTinyEditor = () => {
      if (!window.tinymce || typeof window.tinymce.get !== 'function') {
        return null;
      }

      return window.tinymce.get(editorId);
    };

    const isTinyEditorVisible = (editor) => {
      if (!editor) {
        return false;
      }

      return typeof editor.isHidden !== 'function' || !editor.isHidden();
    };

    const getEditorContent = () => {
      const editor = getTinyEditor();

      if (isTinyEditorVisible(editor)) {
        return editor.getContent();
      }

      return textarea.value;
    };

    const setEditorContent = (content) => {
      const nextContent = content || '';
      const editor = getTinyEditor();

      textarea.value = nextContent;

      if (isTinyEditorVisible(editor)) {
        editor.setContent(nextContent);
      }
    };

    const regionHasContent = (regionKey) => normalizeContent(contentByRegion[regionKey]) !== '';

    const regionHasCustomColor = (regionKey) => normalizeColor(colorByRegion[regionKey] || '') !== '';

    const syncColorJsonField = () => {
      if (colorJsonField) {
        colorJsonField.value = JSON.stringify(colorByRegion);
      }
    };

    const updateSummary = () => {
      if (!selectedMapId) {
        summary.textContent = 'Select a map to begin adding content.';
        return;
      }

      const filledCount = buttons.filter((button) => regionHasContent(button.dataset.mapStudioRegionKey || '')).length;
      const coloredCount = buttons.filter((button) => regionHasCustomColor(button.dataset.mapStudioRegionKey || '')).length;
      summary.textContent = `${filledCount} of ${buttons.length} regions have content; ${coloredCount} have custom colors`;
    };

    const updateButtons = () => {
      buttons.forEach((button) => {
        const regionKey = button.dataset.mapStudioRegionKey || '';
        button.classList.toggle('is-selected', regionKey === selectedRegionKey);
        button.classList.toggle('has-content', regionHasContent(regionKey));
        button.classList.toggle('has-custom-color', regionHasCustomColor(regionKey));
        button.setAttribute('aria-pressed', regionKey === selectedRegionKey ? 'true' : 'false');
      });

      updateSummary();
    };

    const updateRegionColorControls = () => {
      if (!colorPanel || !colorToggle || !colorInput) {
        return;
      }

      const hasRegion = selectedRegionKey !== '';
      const customColor = hasRegion ? normalizeColor(colorByRegion[selectedRegionKey] || '') : '';

      colorPanel.classList.toggle('is-disabled', !hasRegion);
      colorToggle.disabled = !hasRegion;
      colorInput.disabled = !hasRegion;
      colorToggle.checked = customColor !== '';
      colorInput.value = customColor || defaultRegionColor;
    };

    const storeSelectedContent = () => {
      if (!selectedRegionKey) {
        return;
      }

      const content = normalizeContent(getEditorContent());

      if (content === '') {
        delete contentByRegion[selectedRegionKey];
      } else {
        contentByRegion[selectedRegionKey] = content;
      }

      jsonField.value = JSON.stringify(contentByRegion);
      updateButtons();
    };

    const storeSelectedColor = () => {
      if (!colorJsonField || !colorToggle || !colorInput || !selectedRegionKey) {
        return;
      }

      const color = normalizeColor(colorInput.value);

      if (colorToggle.checked && color !== '') {
        colorByRegion[selectedRegionKey] = color;
      } else {
        delete colorByRegion[selectedRegionKey];
      }

      syncColorJsonField();
      updateButtons();
    };

    const selectRegion = (regionKey) => {
      if (!regionKey || regionKey === selectedRegionKey) {
        return;
      }

      storeSelectedContent();
      storeSelectedColor();
      selectedRegionKey = regionKey;
      setEditorContent(contentByRegion[selectedRegionKey] || '');
      updateRegionColorControls();
      updateButtons();
    };

    const buildRegionButton = (shape) => {
      const button = document.createElement('button');
      const label = document.createElement('span');
      const status = document.createElement('span');

      button.type = 'button';
      button.className = 'map-studio-admin__region-button';
      button.dataset.mapStudioRegionKey = shape.key || '';
      button.setAttribute('aria-pressed', 'false');

      label.className = 'map-studio-admin__region-label';
      label.textContent = shape.label || shape.key || '';

      status.className = 'map-studio-admin__status';
      status.setAttribute('aria-hidden', 'true');

      button.append(label, status);
      button.addEventListener('click', () => {
        selectRegion(button.dataset.mapStudioRegionKey || '');
      });

      return button;
    };

    const renderRegionButtons = () => {
      const shapes = shapesForSelectedMap();
      regionList.innerHTML = '';
      buttons = shapes.map(buildRegionButton);
      buttons.forEach((button) => regionList.append(button));

      if (!selectedRegionKey && shapes.length > 0) {
        selectedRegionKey = shapes[0].key || '';
      }

      if (!shapes.some((shape) => shape.key === selectedRegionKey)) {
        selectedRegionKey = shapes[0] ? shapes[0].key || '' : '';
      }

      updateRegionColorControls();
      updateButtons();
    };

    const bindTinyEditor = (editor) => {
      if (!editor || editor.mapStudioBound) {
        return;
      }

      editor.mapStudioBound = true;
      editor.on('change keyup input undo redo', storeSelectedContent);
    };

    if (mapSelect && !isLocked) {
      mapSelect.addEventListener('change', () => {
        selectedMapId = mapSelect.value || '';
        selectedRegionKey = '';
        contentByRegion = {};
        colorByRegion = {};
        jsonField.value = '{}';
        syncColorJsonField();
        renderRegionButtons();
        setEditorContent(selectedRegionKey ? contentByRegion[selectedRegionKey] || '' : '');
      });
    }

    textarea.addEventListener('input', storeSelectedContent);

    if (colorInput && colorToggle) {
      colorInput.addEventListener('input', () => {
        colorToggle.checked = true;
        storeSelectedColor();
      });
      colorToggle.addEventListener('change', storeSelectedColor);
    }

    if (window.tinymce && typeof window.tinymce.on === 'function') {
      window.tinymce.on('AddEditor', (event) => {
        if (event.editor && event.editor.id === editorId) {
          bindTinyEditor(event.editor);
        }
      });
    }

    bindTinyEditor(getTinyEditor());

    if (form) {
      form.addEventListener('submit', () => {
        storeSelectedContent();
        storeSelectedColor();
      });
    }

    renderRegionButtons();
    setEditorContent(selectedRegionKey ? contentByRegion[selectedRegionKey] || '' : '');
    updateRegionColorControls();
    updateButtons();
  };

  document.addEventListener('DOMContentLoaded', () => {
    adminRoots().forEach(initAdmin);
  });
})();
