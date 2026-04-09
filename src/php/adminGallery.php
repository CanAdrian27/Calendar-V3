<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Admin — Images</title>
	<?php include('adminSharedStyles.php'); ?>
	<style>
		/* ── Crop modal ───────────────────────────────────────────────────── */
		#crop-modal {
			display: none;
			position: fixed;
			inset: 0;
			background: rgba(0,0,0,0.9);
			z-index: 1000;
			flex-direction: column;
			align-items: center;
			justify-content: center;
			padding: 16px;
			box-sizing: border-box;
			gap: 14px;
		}
		#crop-modal.open { display: flex; }
		#crop-hint {
			color: rgba(255,255,255,0.75);
			font-size: 13px;
			text-align: center;
			margin: 0;
		}
		#crop-frame {
			width: min(92vw, 780px);
			aspect-ratio: 2 / 1;
			position: relative;
			overflow: hidden;
			cursor: grab;
			/* checkerboard = transparent area indicator */
			background-color: #777;
			background-image:
				linear-gradient(45deg, #999 25%, transparent 25%),
				linear-gradient(-45deg, #999 25%, transparent 25%),
				linear-gradient(45deg, transparent 75%, #999 75%),
				linear-gradient(-45deg, transparent 75%, #999 75%);
			background-size: 18px 18px;
			background-position: 0 0, 0 9px, 9px -9px, -9px 0;
			/* border to show the crop boundary clearly */
			outline: 3px solid rgba(255,255,255,0.6);
		}
		#crop-frame.dragging { cursor: grabbing; }
		#crop-full-img {
			position: absolute;
			touch-action: none;
			user-select: none;
			pointer-events: none; /* frame handles drag, not the image */
		}
		#crop-controls {
			display: flex;
			gap: 12px;
			align-items: center;
			flex-wrap: wrap;
			justify-content: center;
		}
		#crop-controls label {
			color: #fff;
			font-size: 13px;
			display: flex;
			align-items: center;
			gap: 8px;
		}
		#crop-zoom-slider { width: 130px; }
		.btn-crop-ok     { background:#2e7d32; color:#fff; border:none; padding:10px 22px; border-radius:6px; font-size:15px; cursor:pointer; }
		.btn-crop-cancel { background:#555;    color:#fff; border:none; padding:10px 22px; border-radius:6px; font-size:15px; cursor:pointer; }
	</style>
</head>
<body>
<?php include('adminNav.php'); ?>

<h1>Background Images</h1>

<div class="card">
	<h2>Upload Images</h2>
	<div class="upload-zone" id="upload-zone">
		<p>Drag &amp; drop images here, or click to select<br><small style="color:#888">JPG, PNG, WEBP, HEIC supported</small></p>
		<button type="button" class="btn-secondary" onclick="document.getElementById('img-file').click()">Choose Files</button>
		<input type="file" id="img-file" accept="image/*,.heic,.heif" multiple style="display:none" onchange="uploadFiles(this.files)">
		<div id="upload-progress" style="margin-top:12px;font-size:13px;color:#555;min-height:20px;white-space:pre-wrap;line-height:1.6"></div>
	</div>
</div>

<div class="card">
	<h2>Gallery</h2>
	<div id="gallery-grid" class="gallery-grid">
		<p style="color:#888;font-size:13px">Loading images…</p>
	</div>
	<div style="display:flex;gap:10px;align-items:center;margin-top:16px">
		<button type="button" class="btn-secondary" onclick="processImages()">⚙ Process Images</button>
		<span id="process-status" style="font-size:13px;color:#555"></span>
	</div>
</div>

<!-- ── Crop modal ────────────────────────────────────────────────────────── -->
<div id="crop-modal">
	<p id="crop-hint">Drag to position the image · Use slider to zoom · Transparent areas saved as-is</p>
	<div id="crop-frame">
		<img id="crop-full-img" alt="" draggable="false">
	</div>
	<div id="crop-controls">
		<label>Zoom <input type="range" id="crop-zoom-slider" min="25" max="300" value="100"></label>
		<button class="btn-crop-ok"     onclick="confirmCrop()">✓ Apply Crop</button>
		<button class="btn-crop-cancel" onclick="closeCropModal()">✕ Cancel</button>
	</div>
</div>

<script>
// ── Gallery ──────────────────────────────────────────────────────────────────
function loadGallery() {
	fetch('adminImages.php?action=list')
		.then(r => r.json())
		.then(images => {
			var grid = document.getElementById('gallery-grid');
			if (!images.length) {
				grid.innerHTML = '<p style="color:#888;font-size:13px">No images found in images/</p>';
				return;
			}
			grid.innerHTML = images.map(img => `
				<div class="gallery-item" id="img-${img.file}">
					<img src="adminImageThumb.php?f=${encodeURIComponent(img.file)}" alt="${img.file}" loading="lazy">
					<div class="img-meta">
						<div class="img-dims">${img.width} × ${img.height} &nbsp;·&nbsp; ${(img.width/img.height).toFixed(1)}:1</div>
						<span class="img-status ${img.hasSupport ? '' : 'unprocessed'}">${img.hasSupport ? 'Ready' : 'Pending'}</span>
						<button class="btn-crop" title="Rotate 90° counter-clockwise" onclick="rotateImage('${img.file}', -90, this)">↺</button>
						<button class="btn-crop" title="Rotate 90° clockwise" onclick="rotateImage('${img.file}', 90, this)">↻</button>
						<button class="btn-crop" title="Crop to 2:1" onclick="openCropModal('${img.file}')">✏</button>
						<button class="btn-remove" style="font-size:14px;padding:4px 8px" onclick="deleteImage('${img.file}')">×</button>
					</div>
				</div>`).join('');
		})
		.catch(() => {
			document.getElementById('gallery-grid').innerHTML = '<p style="color:#c00;font-size:13px">Failed to load images.</p>';
		});
}

function deleteImage(file) {
	if (!confirm('Delete ' + file + '?')) return;
	var fd = new FormData();
	fd.append('action', 'delete');
	fd.append('file', file);
	fetch('adminImages.php', { method: 'POST', body: fd })
		.then(r => r.json())
		.then(res => {
			if (res.ok) { var el = document.getElementById('img-' + file); if (el) el.remove(); }
			else alert('Delete failed: ' + (res.error || 'unknown error'));
		});
}

function uploadFiles(files) {
	if (!files || !files.length) return;
	var progress = document.getElementById('upload-progress');
	var done = 0, total = files.length;
	progress.textContent = 'Uploading 0 / ' + total + '…';
	Array.from(files).forEach(file => {
		var fd = new FormData();
		fd.append('action', 'upload');
		fd.append('image', file);
		fetch('adminImages.php', { method: 'POST', body: fd })
			.then(r => r.json())
			.then(res => {
				done++;
				if (res.ok) {
					progress.textContent = done < total ? 'Uploading ' + done + ' / ' + total + '…' : '✓ All done';
					if (done === total) loadGallery();
				} else {
					var msg = '⚠ ' + file.name + ' failed:\n' + (res.error || 'Upload failed');
					if (res.php_upload_limit) msg += '\n\nServer limits — upload_max_filesize: ' + res.php_upload_limit + '  post_max_size: ' + res.php_post_limit;
					progress.style.color = '#c00';
					progress.textContent = msg;
				}
			})
			.catch(() => { progress.style.color = '#c00'; progress.textContent = '⚠ Upload request failed (network error).'; });
	});
}

var zone = document.getElementById('upload-zone');
zone.addEventListener('dragover',  e => { e.preventDefault(); zone.classList.add('drag-over'); });
zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
zone.addEventListener('drop', e => { e.preventDefault(); zone.classList.remove('drag-over'); uploadFiles(e.dataTransfer.files); });

function rotateImage(file, dir, btn) {
	btn.disabled = true;
	btn.textContent = '…';
	var fd = new FormData();
	fd.append('action', 'rotate');
	fd.append('file', file);
	fd.append('dir', dir);
	fetch('adminImages.php', { method: 'POST', body: fd })
		.then(r => r.json())
		.then(res => {
			if (res.ok) { location.reload(); }
			else { alert('Rotate failed: ' + (res.error || 'unknown error')); btn.disabled = false; btn.textContent = dir === 90 ? '↻' : '↺'; }
		})
		.catch(() => { alert('Rotate request failed.'); btn.disabled = false; btn.textContent = dir === 90 ? '↻' : '↺'; });
}

function processImages() {
	var status = document.getElementById('process-status');
	status.textContent = 'Processing…';
	fetch('processImages.php', { method: 'POST' })
		.then(r => r.text())
		.then(() => { status.textContent = '✓ Done'; loadGallery(); })
		.catch(() => { status.textContent = '⚠ Failed'; });
}

// ── Crop modal ───────────────────────────────────────────────────────────────
var cropFile  = null;
var cropOrigW = 0, cropOrigH = 0;
var cropImgX  = 0, cropImgY  = 0; // current display position within frame
var isDragging = false, dragStartX, dragStartY, dragImgX, dragImgY;

var cropFrame = document.getElementById('crop-frame');
var cropImg   = document.getElementById('crop-full-img');

function openCropModal(file) {
	cropFile = file;
	cropImg.onload = function() {
		cropOrigW = cropImg.naturalWidth;
		cropOrigH = cropImg.naturalHeight;
		document.getElementById('crop-zoom-slider').value = 100;
		document.getElementById('crop-modal').classList.add('open');
		applyZoom(100); // must be after modal is visible so offsetWidth/Height are non-zero
	};
	cropImg.src = 'images/' + file + '?t=' + Date.now();
}

function closeCropModal() {
	document.getElementById('crop-modal').classList.remove('open');
	cropFile = null;
}

function applyZoom(pct) {
	var frameW = cropFrame.offsetWidth;
	var frameH = cropFrame.offsetHeight;
	// Image height = frame height * zoom, width proportional
	var imgH = frameH * (pct / 100);
	var imgW = imgH * (cropOrigW / cropOrigH);
	cropImg.style.width  = imgW + 'px';
	cropImg.style.height = imgH + 'px';
	// Centre image in frame
	cropImgX = (frameW - imgW) / 2;
	cropImgY = (frameH - imgH) / 2;
	cropImg.style.left = cropImgX + 'px';
	cropImg.style.top  = cropImgY + 'px';
}

document.getElementById('crop-zoom-slider').addEventListener('input', function() {
	applyZoom(parseInt(this.value));
});

function getPoint(e) {
	return e.touches ? { x: e.touches[0].clientX, y: e.touches[0].clientY }
	                 : { x: e.clientX,             y: e.clientY };
}

cropFrame.addEventListener('mousedown',  startDrag);
cropFrame.addEventListener('touchstart', startDrag, { passive: false });

function startDrag(e) {
	e.preventDefault();
	isDragging = true;
	var pt = getPoint(e);
	dragStartX = pt.x;
	dragStartY = pt.y;
	dragImgX   = cropImgX;
	dragImgY   = cropImgY;
	cropFrame.classList.add('dragging');
}

document.addEventListener('mousemove',  doDrag);
document.addEventListener('touchmove',  doDrag, { passive: false });

function doDrag(e) {
	if (!isDragging) return;
	e.preventDefault();
	var pt = getPoint(e);
	cropImgX = dragImgX + (pt.x - dragStartX);
	cropImgY = dragImgY + (pt.y - dragStartY);
	cropImg.style.left = cropImgX + 'px';
	cropImg.style.top  = cropImgY + 'px';
}

document.addEventListener('mouseup',  endDrag);
document.addEventListener('touchend', endDrag);

function endDrag() {
	isDragging = false;
	cropFrame.classList.remove('dragging');
}

function confirmCrop() {
	var file   = cropFile;
	var frameW = cropFrame.offsetWidth;
	var frameH = cropFrame.offsetHeight;

	// Scale: how many original pixels per frame display pixel
	// Canvas is always origH*2 × origH; frame displays that at frameW × frameH
	var scaleX = (cropOrigH * 2) / frameW;
	var scaleY =  cropOrigH      / frameH;

	// Image position and size in canvas coordinates (full original resolution)
	var imgCanvasX = Math.round(cropImgX * scaleX);
	var imgCanvasY = Math.round(cropImgY * scaleY);
	var imgCanvasW = Math.round(parseFloat(cropImg.style.width)  * scaleX);
	var imgCanvasH = Math.round(parseFloat(cropImg.style.height) * scaleY);

	// Cap output canvas at 2160px wide (2× display width) — phone photos can be
	// 6000+ px which exhausts ImageMagick cache on the Pi. All coords scale together.
	var rawCanvasW = cropOrigH * 2;
	var rawCanvasH = cropOrigH;
	var outputScale = Math.min(1, 2160 / rawCanvasW);

	closeCropModal();

	var fd = new FormData();
	fd.append('file',      file);
	fd.append('canvas_w',  Math.round(rawCanvasW * outputScale));
	fd.append('canvas_h',  Math.round(rawCanvasH * outputScale));
	fd.append('img_x',     Math.round(imgCanvasX * outputScale));
	fd.append('img_y',     Math.round(imgCanvasY * outputScale));
	fd.append('img_w',     Math.round(imgCanvasW * outputScale));
	fd.append('img_h',     Math.round(imgCanvasH * outputScale));

	fetch('adminCropImage.php', { method: 'POST', body: fd })
		.then(r => r.text().then(body => {
			try {
				var res = JSON.parse(body);
				if (res.ok) { location.reload(); return; }
				alert('Crop failed: ' + (res.error || 'unknown error'));
			} catch(e) {
				alert('Crop failed (server error):\n\n' + body.substring(0, 600));
			}
		}))
		.catch(err => alert('Crop request failed (network error):\n' + err));
}

// Close on backdrop click
document.getElementById('crop-modal').addEventListener('click', function(e) {
	if (e.target === this) closeCropModal();
});

loadGallery();
</script>
</body>
</html>
