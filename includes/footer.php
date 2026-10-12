<?php $jsVersion=(string)(@filemtime(APP_ROOT.'/assets/js/app.js')?:time());$institutionName=app_setting('institution_name','Nigerian Army University, Biu');$portalName=app_setting('portal_name',APP_NAME); ?>
<?php if(!empty($shell)): ?>
  </main><footer class="app-footer"><span><?= e($institutionName) ?></span><span><?= e($portalName) ?></span></footer>
 </div>
</div>
<?php elseif(!empty($bareLayout)): ?>
</div></main>
<?php else: ?>
</div><footer class="auth-footer">© <?= date('Y') ?> <?= e($institutionName) ?> · Borno State, Nigeria</footer></section></main>
<?php endif; ?>
<script src="<?= e(url('assets/vendor/bootstrap/bootstrap.bundle.min.js')) ?>"></script><script src="<?= e(url('assets/js/app.js')) ?>?v=<?= e($jsVersion) ?>"></script></body></html>
