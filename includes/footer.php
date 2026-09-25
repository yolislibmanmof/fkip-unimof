    </main>

    <footer class="footer">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-col">
                    <h3><?= sanitize(get_setting('singkatan', 'FKIP UNIMOF')) ?></h3>
                    <p><?= sanitize(get_setting('nama_fakultas')) ?><br><?= sanitize(get_setting('nama_universitas')) ?></p>
                </div>
                <div class="footer-col">
                    <h4>Kontak</h4>
                    <ul class="contact-list">
                        <li>📍 <?= sanitize(get_setting('alamat', 'Maumere, NTT')) ?></li>
                        <li>📞 <?= sanitize(get_setting('telepon', '(0382) 21234')) ?></li>
                        <li>✉️ <?= sanitize(get_setting('email', 'fkip@unimof.ac.id')) ?></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?= date('Y') ?> FKIP UNIMOF. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <button class="back-to-top" id="backToTop">↑</button>
    <script src="<?= asset('js/main.js') ?>" defer></script>
</body>
</html>