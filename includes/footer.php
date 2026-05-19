<?php
// includes/footer.php
$prefix = (strpos($_SERVER['SCRIPT_NAME'], '/user/') !== false) ? '../' : '';
?>
<footer style="text-align:center; padding: 40px 20px; background: rgba(11, 13, 18, 0.9); color:#7a7a7a; border-top: 1px solid rgba(255,255,255,0.05); margin-top: 80px;">
    <div style="max-width:1200px; margin:0 auto; display:flex; flex-direction:column; gap:30px;">
        

        <div style="display:flex; justify-content:center; gap:30px; flex-wrap:wrap;">
            <a href="<?php echo $prefix; ?>index.php" style="color:#aaa; text-decoration:none;">Inicio</a>
            <a href="<?php echo $prefix; ?>productos.php" style="color:#aaa; text-decoration:none;">Productos</a>
            <a href="<?php echo $prefix; ?>login.php" style="color:#aaa; text-decoration:none;">Login</a>
            <a href="<?php echo $prefix; ?>register.php" style="color:#aaa; text-decoration:none;">Registro</a>
            <a href="<?php echo $prefix; ?>recargar.php" style="color:#aaa; text-decoration:none;">Recargar</a>
            <a href="#" style="color:#aaa; text-decoration:none;">Términos</a>
            <a href="#" style="color:#aaa; text-decoration:none;">Privacidad</a>
            <a href="#" style="color:#aaa; text-decoration:none;">Soporte</a>
        </div>

        <div style="font-size:0.9rem; color:#666;">
            © 2024 Monkeystraming. Todos los derechos reservados.<br>
            Streaming de calidad para todos.
        </div>
    </div>
</footer>

<script>
const searchInput = document.getElementById('searchInput');
if (searchInput) {
    searchInput.addEventListener('input', function(e) {
        const term = (e.target.value || '').toLowerCase();
        if (term.length > 2) {
            console.log('Buscando:', term);
        }
    });
}
</script>

</body>
</html>
