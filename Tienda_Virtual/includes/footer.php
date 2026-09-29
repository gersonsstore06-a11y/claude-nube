</div>
<?php
$scriptsPath = __DIR__ . '/../public/js/app.js';
$scriptsVersion = is_file($scriptsPath) ? (string) filemtime($scriptsPath) : '1';
?>
<script src="<?php echo BASE_URL; ?>/public/js/app.js?v=<?php echo urlencode($scriptsVersion); ?>"></script>
</body>
</html>
