            </div>
        </main>
    </div>

    <div id="modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Modal</h2>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div id="modalBody" class="modal-body"></div>
        </div>
    </div>

    <div id="toast-container"></div>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>const APP_URL = <?= json_encode(APP_URL) ?>;</script>
    <script src="<?= APP_URL ?>/assets/js/app.js"></script>
    <?php if (!empty($extraScripts)): ?>
        <?php foreach ((array) $extraScripts as $script): ?>
    <script src="<?= APP_URL ?>/<?= ltrim($script, '/') ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
    <?php if (isset($pageScript)): ?>
    <script><?= $pageScript ?></script>
    <?php endif; ?>
</body>
</html>
