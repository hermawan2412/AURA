  </main>
</div>
<script src="<?php echo isset($rootAsset) ? $rootAsset : ''; ?>assets/js/ambient-glow.js"></script>
<script src="<?php echo isset($rootAsset) ? $rootAsset : ''; ?>assets/js/theme-toggle.js"></script>
<?php if (!empty($jsTambahan)): ?><script src="<?php echo isset($rootAsset) ? $rootAsset : ''; ?><?php echo htmlspecialchars((string) $jsTambahan); ?>"></script><?php endif; ?>
</body>
</html>
