<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Admin — Notes</title>
	<?php include('adminSharedStyles.php'); ?>
	<link href="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.snow.css" rel="stylesheet">
</head>
<body>
<?php include('adminNav.php'); ?>

<h1>Family Notes</h1>

<div class="card">
	<div id="quill-editor"></div>
	<div style="display:flex;gap:12px;align-items:center">
		<button type="button" class="btn-save" style="width:auto;padding:10px 28px" onclick="saveNotes()">Save Notes</button>
		<span id="notes-status" style="font-size:13px;color:#555"></span>
	</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.min.js"></script>
<script>
var quill = new Quill('#quill-editor', {
	theme: 'snow',
	modules: {
		toolbar: [
			[{ header: [1, 2, 3, false] }],
			['bold', 'italic', 'underline'],
			[{ list: 'ordered' }, { list: 'bullet' }],
			[{ size: ['small', false, 'large', 'huge'] }],
			['clean']
		]
	}
});

fetch('notes/note.html')
	.then(r => r.text())
	.then(html => {
		var match = html.match(/<body[^>]*>([\s\S]*?)<\/body>/i);
		quill.clipboard.dangerouslyPasteHTML(match ? match[1] : html);
	})
	.catch(() => {});

function saveNotes() {
	var status = document.getElementById('notes-status');
	status.textContent = 'Saving…';
	var fd = new FormData();
	fd.append('html', quill.root.innerHTML);
	fetch('adminSaveNotes.php', { method: 'POST', body: fd })
		.then(r => r.json())
		.then(res => { status.textContent = res.ok ? '✓ Saved' : '⚠ ' + res.error; })
		.catch(() => { status.textContent = '⚠ Save failed'; });
}
</script>
</body>
</html>
