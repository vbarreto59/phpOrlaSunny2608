<?php
// includes/footer.php
// Rodapé comum para todas as páginas do sistema
?>

    </div> <footer class="bg-primary text-white mt-5 py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-3 mb-md-0">
                    <h5 class="fw-bold">Captação Imóveis</h5>
                    <p class="small mb-1">
                        Sistema de gerenciamento de captação de imóveis<br>
                        Desenvolvido para uso interno
                    </p>
                    <p class="small mb-0">
                        © <?= date('Y') ?> - Todos os direitos reservados
                    </p>
                </div>

                <div class="col-md-4 mb-3 mb-md-0">
                    <h6 class="fw-bold mb-3">Acesso rápido</h6>
                    <ul class="list-unstyled small">
                        <li><a href="<?= BASE_URL ?>/dash.php" class="text-white text-decoration-none">Dashboard</a></li>
                        <li><a href="<?= BASE_URL ?>/pages/imoveis/list.php" class="text-white text-decoration-none">Lista de Imóveis</a></li>
                        <li><a href="<?= BASE_URL ?>/pages/map/index.php" class="text-white text-decoration-none">Mapa de Imóveis</a></li>
                        <li><a href="<?= BASE_URL ?>/pages/proprietarios/list.php" class="text-white text-decoration-none">Proprietários</a></li>
                    </ul>
                </div>

                <div class="col-md-4">
                    <h6 class="fw-bold mb-3">Suporte</h6>
                    <ul class="list-unstyled small">
                        <li><i class="bi bi-envelope me-2"></i> valterpb@hotmail.com</li>
                        <li><i class="bi bi-telephone me-2"></i> (81) 99999-9999</li>
                        <li class="mt-2">
                            <a href="<?= BASE_URL ?>/logout.php" class="btn btn-outline-light btn-sm">
                                <i class="bi bi-box-arrow-right me-2"></i>Sair do sistema
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <hr class="my-3 opacity-50">
            <div class="text-center small">
                <p class="mb-0">
                    Sistema desenvolvido por <strong>Valter Barreto</strong> • Recife - PE
                </p>
            </div>
        </div>
    </footer>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <?php if (file_exists($_SERVER['DOCUMENT_ROOT'] . BASE_URL . '/js/script.js')): ?>
        <script src="<?= BASE_URL ?>/js/script.js"></script>
    <?php endif; ?>

    <script>
        $(document).ready(function() {
            // Seletor jQuery para os links de logout
            $('a[href*="logout.php"]').on('click', function(e) {
                if (!confirm('Deseja realmente sair do sistema?')) {
                    e.preventDefault();
                }
            });
        });
    </script>

</body>
</html>