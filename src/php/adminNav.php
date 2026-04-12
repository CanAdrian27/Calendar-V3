<nav>
	<a href="admin.php"          <?= basename($_SERVER['PHP_SELF']) === 'admin.php'          ? 'class="active"' : '' ?>>Dashboard</a>
	<a href="adminCalendar.php"  <?= basename($_SERVER['PHP_SELF']) === 'adminCalendar.php'  ? 'class="active"' : '' ?>>Calendar</a>
	<a href="adminGallery.php"   <?= basename($_SERVER['PHP_SELF']) === 'adminGallery.php'   ? 'class="active"' : '' ?>>Images</a>
	<a href="adminRecipe.php"    <?= basename($_SERVER['PHP_SELF']) === 'adminRecipe.php'    ? 'class="active"' : '' ?>>Recipe</a>
	<a href="adminNotes.php"     <?= basename($_SERVER['PHP_SELF']) === 'adminNotes.php'     ? 'class="active"' : '' ?>>Notes</a>
	<a href="adminWord.php"      <?= basename($_SERVER['PHP_SELF']) === 'adminWord.php'      ? 'class="active"' : '' ?>>Word / Quote</a>
	<a href="adminSchedule.php"  <?= basename($_SERVER['PHP_SELF']) === 'adminSchedule.php'  ? 'class="active"' : '' ?>>Schedule</a>
	<a href="adminStats.php"     <?= basename($_SERVER['PHP_SELF']) === 'adminStats.php'     ? 'class="active"' : '' ?>>Stats</a>
</nav>
