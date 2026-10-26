(function () {
  'use strict';

  const roots = document.querySelectorAll('[data-o3d-configurator]');

  function parseStl(buffer) {
    const view = new DataView(buffer);
    const count = view.byteLength >= 84 ? view.getUint32(80, true) : 0;
    const triangles = [];

    if (count > 0 && count <= 50000 && 84 + count * 50 === view.byteLength) {
      let offset = 84;
      for (let i = 0; i < count; i += 1) {
        offset += 12;
        const triangle = [];
        for (let vertex = 0; vertex < 3; vertex += 1) {
          triangle.push([
            view.getFloat32(offset, true),
            view.getFloat32(offset + 4, true),
            view.getFloat32(offset + 8, true)
          ]);
          offset += 12;
        }
        triangles.push(triangle);
        offset += 2;
      }
      return triangles;
    }

    const text = new TextDecoder().decode(buffer);
    const vertices = [];
    const expression = /vertex\s+([-+\d.eE]+)\s+([-+\d.eE]+)\s+([-+\d.eE]+)/gi;
    let match;
    while ((match = expression.exec(text)) !== null) {
      vertices.push([Number(match[1]), Number(match[2]), Number(match[3])]);
      if (vertices.length > 150000) {
        throw new Error('Model exceeds the preview limit.');
      }
    }
    if (!vertices.length || vertices.length % 3 !== 0) {
      throw new Error('Unsupported or invalid STL model.');
    }
    for (let i = 0; i < vertices.length; i += 3) {
      triangles.push(vertices.slice(i, i + 3));
    }
    return triangles;
  }

  function initializePreview(root) {
    const canvas = root.querySelector('[data-o3d-canvas]');
    const url = root.dataset.previewUrl;
    if (!canvas || !url) return null;

    const context = canvas.getContext('2d');
    const preview = canvas.parentElement;
    const model = { triangles: [], rotationX: -0.45, rotationY: 0.65, zoom: 1, scale: 1 };
    let dragging = false;
    let previous = null;

    function draw() {
      const width = preview.clientWidth;
      const height = preview.clientHeight;
      if (!width || !height) return;
      const ratio = Math.min(window.devicePixelRatio || 1, 2);
      canvas.width = width * ratio;
      canvas.height = height * ratio;
      context.setTransform(ratio, 0, 0, ratio, 0, 0);
      context.clearRect(0, 0, width, height);
      context.fillStyle = '#f5f6f8';
      context.fillRect(0, 0, width, height);
      if (!model.triangles.length) return;

      const bounds = [[Infinity, -Infinity], [Infinity, -Infinity], [Infinity, -Infinity]];
      model.triangles.forEach((triangle) => {
        triangle.forEach((vertex) => {
          vertex.forEach((coordinate, axis) => {
            bounds[axis][0] = Math.min(bounds[axis][0], coordinate);
            bounds[axis][1] = Math.max(bounds[axis][1], coordinate);
          });
        });
      });
      const center = bounds.map(([min, max]) => (min + max) / 2);
      const size = Math.max(...bounds.map(([min, max]) => max - min)) || 1;
      const fit = Math.min(width, height) * 0.72 / size * model.zoom * model.scale;
      const cosX = Math.cos(model.rotationX);
      const sinX = Math.sin(model.rotationX);
      const cosY = Math.cos(model.rotationY);
      const sinY = Math.sin(model.rotationY);
      const projected = model.triangles.map((triangle) => {
        const points = triangle.map((vertex) => {
          const x = (vertex[0] - center[0]) * fit;
          const y = (vertex[1] - center[1]) * fit;
          const z = (vertex[2] - center[2]) * fit;
          const rx = x * cosY - z * sinY;
          const rz = x * sinY + z * cosY;
          return [rx, y * cosX - rz * sinX, y * sinX + rz * cosX];
        });
        return { points, depth: points.reduce((sum, point) => sum + point[2], 0) / 3 };
      }).sort((a, b) => a.depth - b.depth);

      projected.forEach(({ points }) => {
        const light = Math.max(0.25, Math.min(1, 0.55 + (points[0][1] - points[1][1]) / fit));
        context.beginPath();
        context.moveTo(width / 2 + points[0][0], height / 2 - points[0][1]);
        context.lineTo(width / 2 + points[1][0], height / 2 - points[1][1]);
        context.lineTo(width / 2 + points[2][0], height / 2 - points[2][1]);
        context.closePath();
        context.fillStyle = `rgb(${Math.round(130 * light)}, ${Math.round(145 * light)}, ${Math.round(165 * light)})`;
        context.fill();
        context.strokeStyle = 'rgba(35, 45, 60, 0.15)';
        context.stroke();
      });
    }

    const timeout = new AbortController();
    const timeoutId = window.setTimeout(() => timeout.abort(), 20000);
    fetch(url, { credentials: 'same-origin', signal: timeout.signal })
      .then((response) => {
        if (!response.ok) throw new Error('Preview file could not be loaded.');
        const contentLength = Number(response.headers.get('Content-Length'));
        if (Number.isFinite(contentLength) && contentLength > 25 * 1024 * 1024) {
          throw new Error('Preview file exceeds the size limit.');
        }
        return response.arrayBuffer();
      })
      .then((buffer) => {
        if (buffer.byteLength > 25 * 1024 * 1024) throw new Error('Preview file exceeds the size limit.');
        model.triangles = parseStl(buffer);
        const message = root.querySelector('[data-o3d-preview-message]');
        if (message) message.hidden = true;
        draw();
      })
      .catch((error) => {
        const message = root.querySelector('[data-o3d-preview-message]');
        if (message) {
          message.textContent = o3dSettings.strings.preview;
          message.hidden = false;
          message.setAttribute('title', error.message);
        }
      })
      .finally(() => {
        window.clearTimeout(timeoutId);
      });

    canvas.addEventListener('pointerdown', (event) => {
      dragging = true;
      previous = [event.clientX, event.clientY];
      canvas.setPointerCapture(event.pointerId);
    });
    canvas.addEventListener('pointermove', (event) => {
      if (!dragging) return;
      model.rotationY += (event.clientX - previous[0]) * 0.01;
      model.rotationX += (event.clientY - previous[1]) * 0.01;
      previous = [event.clientX, event.clientY];
      draw();
    });
    canvas.addEventListener('pointerup', () => { dragging = false; });
    canvas.addEventListener('pointercancel', () => { dragging = false; });
    canvas.addEventListener('wheel', (event) => {
      event.preventDefault();
      model.zoom = Math.max(0.4, Math.min(3, model.zoom * (event.deltaY < 0 ? 1.1 : 0.9)));
      draw();
    }, { passive: false });
    window.addEventListener('resize', draw);

    return {
      setScale(value) {
        model.scale = Math.max(0.1, Math.min(5, value));
        draw();
      }
    };
  }

  roots.forEach((root) => {
    const form = root.closest('form.cart');
    const scaleInput = root.querySelector('[name="o3d_scale"]');
    const priceOutput = root.querySelector('[data-o3d-price]');
    const dimensionsOutput = root.querySelector('[data-o3d-dimensions]');
    const errorOutput = root.querySelector('[data-o3d-error]');
    const preview = initializePreview(root);
    let pending = null;

    function updateQuote() {
      if (!form || !scaleInput) return;
      if (pending) pending.abort();
      pending = new AbortController();
      errorOutput.hidden = true;
      priceOutput.textContent = o3dSettings.strings.loading;

      const options = {};
      root.querySelectorAll('select[name^="o3d_options["]').forEach((select) => {
        const match = select.name.match(/^o3d_options\[([^\]]+)\]$/);
        if (match) options[match[1]] = select.value;
      });
      const scale = Number(scaleInput.value);
      if (preview && Number.isFinite(scale)) preview.setScale(scale);

      fetch(o3dSettings.quoteUrl, {
        method: 'POST',
        credentials: 'same-origin',
        signal: pending.signal,
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': o3dSettings.nonce
        },
        body: JSON.stringify({
          product_id: Number(root.dataset.productId),
          scale: scaleInput.value,
          options
        })
      })
        .then(async (response) => {
          const data = await response.json();
          if (!response.ok) throw new Error(data.message || o3dSettings.strings.error);
          return data;
        })
        .then((data) => {
          priceOutput.textContent = data.formatted;
          dimensionsOutput.textContent = `ابعاد (mm): ${data.dimensions.x} × ${data.dimensions.y} × ${data.dimensions.z}`;
        })
        .catch((error) => {
          if (error.name === 'AbortError') return;
          priceOutput.textContent = '—';
          errorOutput.textContent = error.message || o3dSettings.strings.error;
          errorOutput.hidden = false;
        });
    }

    root.addEventListener('change', updateQuote);
    root.addEventListener('input', (event) => {
      if (event.target === scaleInput) updateQuote();
    });
    if (form) {
      form.addEventListener('submit', (event) => {
        if (errorOutput.hidden) return;
        event.preventDefault();
        errorOutput.scrollIntoView({ behavior: 'smooth', block: 'center' });
      });
    }
    updateQuote();
  });
})();
