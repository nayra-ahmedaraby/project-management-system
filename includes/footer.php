            </div><!-- End page-content -->
        </main>
    </div><!-- End app-container -->
    
    <script src="assets/js/app.js"></script>
    <?php if (isset($extraScripts)): ?>
        <?php foreach ($extraScripts as $script): ?>
            <script src="<?php echo $script; ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
