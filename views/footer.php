        </div>
    </main>
    
    <footer class="main-footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>Medicare</h3>
                    <p>Your trusted healthcare partner. Find the best doctors and medical services in your area.</p>
                </div>
                
                <div class="footer-section">
                    <h3>Quick Links</h3>
                    <ul>
                        <li><a href="/about.php">About Us</a></li>
                        <li><a href="/services.php">Our Services</a></li>
                        <li><a href="/doctors.php">Find Doctors</a></li>
                        <li><a href="/contact.php">Contact Us</a></li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h3>Services</h3>
                    <ul>
                        <li><a href="/services.php?category=consultations">Online Consultations</a></li>
                        <li><a href="/services.php?category=emergency">Emergency Care</a></li>
                        <li><a href="/services.php?category=specialist">Specialist Appointments</a></li>
                        <li><a href="/services.php?category=labs">Lab Tests</a></li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h3>Contact</h3>
                    <p>
                        <i class="fas fa-phone"></i> +1 234 567 890<br>
                        <i class="fas fa-envelope"></i> contact@medicare.com<br>
                        <i class="fas fa-map-marker-alt"></i> 123 Healthcare St, Medical City
                    </p>
                    <div class="social-links">
                        <a href="#"><i class="fab fa-facebook"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-linkedin"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> Medicare. All rights reserved.</p>
                <div class="footer-bottom-links">
                    <a href="/privacy.php">Privacy Policy</a>
                    <a href="/terms.php">Terms of Service</a>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="/public/js/main.js"></script>
    <?php if (isset($pageScripts)): ?>
        <?php foreach ($pageScripts as $script): ?>
            <script src="<?php echo $script; ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>