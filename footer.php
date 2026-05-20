<footer class="footer">
    <p>
        <strong>OmnesEvent</strong> &mdash; Vie associative Omnes
        &middot; Projet Web Dynamique ING2
    </p>
</footer>

<!-- Bannière cookie (TP10 : cookie simple) -->
<?php if (!isset($_COOKIE['cookie_ok'])) : ?>
<div id="cookie-banner" class="cookie-banner">
    <img class="cookie-icon" src="images/cookie.webp" alt="Cookie" width="64" height="64">
    <p>Ce site utilise des cookies pour gérer ta connexion et tes préférences.</p>
    <button class="btn primary btn-sm" id="cookie-ok-btn">J'ai compris</button>
</div>
<?php endif; ?>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"
        integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo="
        crossorigin="anonymous"></script>
<script src="js/script.js?v=4"></script>
</body>
</html>
