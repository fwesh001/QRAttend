<?php
/**
 * QRAttend :: Shared Layout Footer
 * -----------------------------------------------------------------------------
 * Closes the document body/html and loads the Bootstrap 5 JS bundle (Popper
 * included) plus the global main.js helper.
 */
?>
    <footer class="py-3 mt-auto bg-white border-top">
        <div class="container text-center">
            <small class="text-muted">
                &copy; <?= date('Y') ?> <?= sanitize_input(INSTITUTION_NAME) ?> &middot;
                <?= sanitize_input(INSTITUTION_DEPT) ?>
            </small>
            <div class="mt-1">
                <small class="text-muted">
                    Developed by
                    <a href="https://www.zabdiel.tech" target="_blank" rel="noopener noreferrer"
                       class="fw-semibold text-decoration-none" style="color:var(--brand-primary);">
                        Dev.ZABDIEL
                    </a>
                </small>
            </div>
        </div>
    </footer>

    <!-- Bootstrap 5 JS Bundle (includes Popper) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <!-- Global application script -->
    <script src="/assets/js/main.js"></script>
</body>
</html>

