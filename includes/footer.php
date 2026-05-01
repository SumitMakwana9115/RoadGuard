<?php if (isset($_SESSION['user_id'])): ?>
        </div> <!-- end content-wrapper -->
        
        <footer style="background-color: var(--card-bg); color: var(--text-muted); padding: 15px 20px; font-size: 13px; margin-top: auto; border-top: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
            <div style="flex: 1; min-width: 250px;">
                <strong><?= APP_NAME ?></strong> &mdash; <?= APP_TAGLINE ?>
            </div>
            <div style="flex: 1; min-width: 350px; text-align: center;">
                GEC Bhavnagar - Web Programming Assignment - Enrollment: <?= ENROLLMENT_NO ?>
            </div>
            <div style="flex: 1; min-width: 350px; text-align: right;">
                U=<?= UNIQUE_CODE ?> | Domain: <?= COMPLAINT_DOMAIN ?> | SLA: <?= INITIAL_RESPONSE_SLA_HOURS ?>h/<?= RESOLUTION_SLA_HOURS ?>h
            </div>
        </footer>
    </section> <!-- end main-content -->
<?php else: ?>
    </div> <!-- end public-wrapper -->
<?php endif; ?>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Toastr -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    
    <script>
        const BASE_URL = '<?= BASE_URL ?>';
    </script>
    <!-- Custom JS -->
    <script src="<?= BASE_URL ?>/assets/js/main.js"></script>
    
    <?php if (isset($_SESSION['flash_message'])): ?>
        <script>
            $(document).ready(function() {
                toastr.<?= $_SESSION['flash_type'] ?? 'info' ?>('<?= addslashes($_SESSION['flash_message']) ?>');
            });
        </script>
        <?php 
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
        ?>
    <?php endif; ?>
</body>
</html>
