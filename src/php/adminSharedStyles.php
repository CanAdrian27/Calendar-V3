<style>
	*, *::before, *::after { box-sizing: border-box; }
	body {
		font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
		background: #f0f2f5;
		color: #1a1a2e;
		margin: 0;
		padding: 24px;
		max-width: 900px;
	}
	h1 { margin: 0 0 20px; font-size: 22px; }
	h2 { font-size: 15px; text-transform: uppercase; letter-spacing: .06em; color: #555; margin: 0 0 14px; }
	nav {
		display: flex;
		gap: 6px;
		margin-bottom: 24px;
		flex-wrap: wrap;
	}
	nav a {
		background: #fff;
		border: 1px solid #d0d5dd;
		border-radius: 6px;
		padding: 6px 14px;
		font-size: 13px;
		font-weight: 600;
		color: #333;
		text-decoration: none;
	}
	nav a:hover  { background: #f0f4ff; border-color: #4f6ef7; color: #4f6ef7; }
	nav a.active { background: #4f6ef7; border-color: #4f6ef7; color: #fff; }
	.card {
		background: #fff;
		border-radius: 10px;
		padding: 20px 24px;
		margin-bottom: 20px;
		box-shadow: 0 1px 4px rgba(0,0,0,.08);
	}
	label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px; }
	input[type=text], input[type=password], input[type=url] {
		width: 100%;
		padding: 8px 10px;
		border: 1px solid #d0d5dd;
		border-radius: 6px;
		font-size: 14px;
		margin-bottom: 12px;
	}
	input[type=text]:focus, input[type=password]:focus, input[type=url]:focus {
		outline: none;
		border-color: #4f6ef7;
		box-shadow: 0 0 0 3px rgba(79,110,247,.15);
	}
	.toggle-row { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
	.toggle-row input[type=checkbox] { width: 16px; height: 16px; margin: 0; }
	.toggle-row label { margin: 0; font-weight: 500; font-size: 14px; }
	#cal-list { display: flex; flex-direction: column; gap: 12px; margin-bottom: 14px; }
	.cal-row {
		display: grid;
		grid-template-columns: 2fr 1fr auto auto;
		gap: 8px;
		align-items: start;
		background: #f8f9fb;
		border: 1px solid #e4e7ec;
		border-radius: 8px;
		padding: 12px;
	}
	.cal-row input { margin-bottom: 0; }
	.cal-row .pp-cell { display: flex; flex-direction: column; align-items: center; gap: 4px; font-size: 11px; color: #666; padding-top: 4px; }
	.cal-row .pp-cell input { width: auto; }
	.btn-remove {
		background: none;
		border: 1px solid #fca5a5;
		color: #ef4444;
		border-radius: 6px;
		padding: 6px 10px;
		cursor: pointer;
		font-size: 18px;
		line-height: 1;
		align-self: center;
	}
	.btn-remove:hover { background: #fef2f2; }
	.btn-add {
		background: #f0f4ff;
		border: 1px dashed #4f6ef7;
		color: #4f6ef7;
		border-radius: 6px;
		padding: 8px 16px;
		cursor: pointer;
		font-size: 13px;
		font-weight: 600;
		width: 100%;
	}
	.btn-add:hover { background: #e0e8ff; }
	.btn-save {
		background: #4f6ef7;
		color: #fff;
		border: none;
		border-radius: 8px;
		padding: 12px 32px;
		font-size: 15px;
		font-weight: 600;
		cursor: pointer;
		width: 100%;
	}
	.btn-save:hover { background: #3a57e8; }
	.btn-secondary {
		background: #f8f9fb;
		color: #333;
		border: 1px solid #d0d5dd;
		border-radius: 8px;
		padding: 10px 24px;
		font-size: 14px;
		font-weight: 600;
		cursor: pointer;
	}
	.btn-secondary:hover { background: #eef0f3; }
	.notice { border-radius: 8px; padding: 12px 16px; margin-bottom: 20px; font-size: 14px; font-weight: 500; }
	.notice.success { background: #ecfdf5; color: #065f46; border: 1px solid #6ee7b7; }
	.notice.error   { background: #fef2f2; color: #991b1b; border: 1px solid #fca5a5; }
	/* Gallery */
	.gallery-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 14px; margin-bottom: 20px; }
	.gallery-item { border: 1px solid #e4e7ec; border-radius: 8px; overflow: hidden; background: #f8f9fb; }
	.gallery-item img { width: 100%; height: 160px; object-fit: cover; display: block; }
	.gallery-item .img-meta { padding: 8px 10px; display: flex; justify-content: space-between; align-items: center; gap: 6px; }
	.gallery-item .img-dims { font-size: 12px; color: #666; }
	.img-status { font-size: 10px; padding: 2px 6px; border-radius: 10px; background: #dcfce7; color: #166534; }
	.img-status.unprocessed { background: #fef9c3; color: #854d0e; }
	.btn-crop { background: none; border: 1px solid #a5b4fc; color: #4f46e5; border-radius: 6px; padding: 4px 7px; cursor: pointer; font-size: 11px; font-weight: 600; }
	.btn-crop:hover { background: #eef2ff; }
	.btn-crop:disabled { opacity: .5; cursor: default; }
	.upload-zone { border: 2px dashed #d0d5dd; border-radius: 8px; padding: 24px; text-align: center; transition: border-color .2s; }
	.upload-zone.drag-over { border-color: #4f6ef7; background: #f0f4ff; }
	.upload-zone p { margin: 0 0 12px; color: #666; font-size: 14px; }
	/* Notes */
	#quill-editor { border: 1px solid #d0d5dd; border-radius: 0 0 6px 6px; min-height: 300px; font-size: 15px; margin-bottom: 14px; }
	.ql-toolbar  { border-radius: 6px 6px 0 0; border-color: #d0d5dd !important; }
	.ql-container { border-color: #d0d5dd !important; border-radius: 0 0 6px 6px; }
</style>
