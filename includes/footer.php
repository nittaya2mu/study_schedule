    </div><!-- /.content -->
  </main>
</div>

<div class="toast-stack" id="toastStack" aria-live="polite"></div>

<script src="assets/js/app.js"></script>
<?php foreach ($pageScripts ?? [] as $js): ?>
<script src="assets/js/<?= e($js) ?>"></script>
<?php endforeach; ?>
</body>
</html>
