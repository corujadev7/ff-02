<?php
// includes/AntiCopy.php - Proteção Anti-Copy

class AntiCopy {
    private static $initialized = false;
    
    // Método principal para adicionar todas as proteções
    public static function protect() {
        if (self::$initialized) return;
        self::$initialized = true;
        
        self::addJavaScript();
        self::addCSS();
    }
    
    // JavaScript de proteção
    private static function addJavaScript() {
        ?>
        <script>
            (function() {
                // 1. Desabilitar clique direito
                document.addEventListener('contextmenu', function(e) {
                    e.preventDefault();
                    return false;
                });
                
                // 2. Desabilitar atalhos de teclado
                document.addEventListener('keydown', function(e) {
                    const forbiddenKeys = ['c', 'C', 'v', 'V', 'x', 'X', 's', 'S', 'u', 'U', 'p', 'P'];
                    if ((e.ctrlKey || e.metaKey) && forbiddenKeys.includes(e.key)) {
                        e.preventDefault();
                        return false;
                    }
                    
                    if (e.key === 'F12' || 
                        (e.ctrlKey && e.shiftKey && (e.key === 'I' || e.key === 'J' || e.key === 'C')) ||
                        (e.ctrlKey && e.key === 'U') ||
                        e.key === 'PrintScreen') {
                        e.preventDefault();
                        return false;
                    }
                });
                
                // 3. Desabilitar seleção de texto
                document.addEventListener('selectstart', function(e) {
                    e.preventDefault();
                    return false;
                });
                
                // 4. Desabilitar arrastar
                document.addEventListener('dragstart', function(e) {
                    e.preventDefault();
                    return false;
                });
                
                // 5. Limpar console em produção
                if (window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1') {
                    console.clear();
                    console.log = function() {};
                    console.error = function() {};
                    console.warn = function() {};
                }
                
                console.log('🔒 Proteção Anti-Copy ativada');
            })();
        </script>
        <?php
    }
    
    // CSS de proteção
    private static function addCSS() {
        ?>
        <style>
            * {
                -webkit-touch-callout: none !important;
                -webkit-user-select: none !important;
                user-select: none !important;
            }
            
            img, video {
                -webkit-user-drag: none !important;
                pointer-events: none !important;
            }
            
            ::selection {
                background: transparent !important;
            }
            
            /* Permitir seleção apenas no código PIX */
            .pix-code, #pixCode, input[readonly].pix-code {
                user-select: all !important;
                -webkit-user-select: all !important;
            }
        </style>
        <?php
    }
}

// Se chamado diretamente, aplicar proteção
if (basename($_SERVER['PHP_SELF']) === 'AntiCopy.php') {
    AntiCopy::protect();
}
?>