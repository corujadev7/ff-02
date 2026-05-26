<?php

// No início do checkout.php, antes do DOCTYPE
session_start();

define('SECURITY_INIT', true);
require_once './includes/security_init.php';
applySecurity('nome_da_pagina');

// Gerar tokens
if (empty($_SESSION['checkout_token'])) {
    $_SESSION['checkout_token'] = bin2hex(random_bytes(32));
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$securityToken = $_SESSION['checkout_token'];
$csrfToken = $_SESSION['csrf_token'];

// Gerar nonce para esta sessão (não pode ser reutilizado)
$pixNonce = bin2hex(random_bytes(32));
$_SESSION['pix_nonce'] = $pixNonce;
$_SESSION['pix_nonce_expires'] = time() + 300; // 5 minutos

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="<?php echo $csrfToken; ?>">
    <meta name="security-token" content="<?php echo $securityToken; ?>">
    <meta name="pix-nonce" content="<?php echo $pixNonce; ?>">
    <title>Flow Pag</title>
    <style>
        :root {
            --theme: #18181b;
            --accent: #e5ff51;
            --bg: #f4f4f5;
            --card: #fff;
            --border: rgba(0, 0, 0, .08);
            --red: #dc2626;
            --maxw: 520px
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0
        }

        html {
            height: 100%
        }

        body {
            font-family: 'Work Sans', Ubuntu, sans-serif;
            background: var(--bg);
            color: #18181b;
            min-height: 100%
        }

        /* Banner topo */
        .banner {
            background: var(--theme);
            color: var(--accent);
            text-align: center;
            padding: 10px;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .5px;
            overflow: hidden;
            white-space: nowrap
        }

        /* Header */
        .header {
            background: var(--card);
            border-bottom: 1px solid var(--border);
            padding: 14px 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: sticky;
            top: 0;
            z-index: 50
        }

        .header__back {
            position: absolute;
            left: 16px;
            text-decoration: none;
            color: #18181b;
            display: flex;
            align-items: center
        }

        .header__title {
            font-size: 16px;
            font-weight: 700
        }

        /* Layout */
        .page {
            max-width: var(--maxw);
            margin: 0 auto;
            padding: 20px 16px 140px
        }

        /* Resumo produto */
        .summary {
            background: var(--card);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 24px;
            border: 1px solid var(--border)
        }

        .summary__row {
            display: flex;
            justify-content: space-between;
            align-items: center
        }

        .summary__plate {
            font-size: 13px;
            color: #6b7280;
            margin-top: 4px
        }

        .summary__label {
            font-size: 14px;
            color: #6b7280
        }

        .summary__value {
            font-size: 20px;
            font-weight: 900
        }

        .summary__divider {
            border: none;
            border-top: 1px dashed #d1d5db;
            margin: 12px 0
        }

        /* Section title */
        .sec-title {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px
        }

        .sec-title h2 {
            font-size: 18px;
            font-weight: 700
        }

        .sec-badge {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            color: #6b7280
        }

        .sec-badge svg {
            width: 20px;
            height: 20px
        }

        /* Step indicator */
        .step {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px
        }

        .step__num {
            width: 32px;
            height: 32px;
            background: var(--theme);
            color: var(--accent);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 900;
            font-size: 14px
        }

        .step__text {
            font-size: 16px;
            font-weight: 500
        }

        /* Form fields */
        .field {
            background: var(--card);
            border-radius: 8px 8px 0 0;
            border-bottom: 4px solid rgba(0, 0, 0, .16);
            padding: 0 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 8px;
            transition: border-color .2s
        }

        .field:focus-within {
            border-color: var(--theme)
        }

        .field svg {
            flex-shrink: 0;
            width: 24px;
            height: 24px
        }

        .field input {
            width: 100%;
            padding: 14px 0;
            border: none;
            outline: none;
            font-size: 15px;
            font-weight: 700;
            background: transparent;
            font-family: inherit
        }

        .field input::placeholder {
            color: #94a3b8;
            font-weight: 400
        }

        .field-error {
            border-color: var(--red) !important
        }

        .error-msg {
            font-size: 12px;
            color: var(--red);
            margin: -4px 0 8px 20px;
            display: none
        }

        /* Confirmar button */
        .btn-confirm {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 12px;
            background: var(--theme);
            color: var(--accent);
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            margin-top: 16px;
            transition: opacity .2s
        }

        .btn-confirm:disabled {
            background: #d1d5db;
            color: #fff;
            cursor: not-allowed
        }

        /* Divider */
        .divider {
            border: none;
            border-top: 4px solid rgba(0, 0, 0, .04);
            margin: 24px 0
        }

        /* Seguranca box */
        .seg-box {
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 14px;
            margin-bottom: 24px
        }

        .seg-box p {
            font-size: 13px;
            color: #18181b;
            line-height: 1.5
        }

        .seg-box ul {
            list-style: none;
            padding: 0;
            margin: 0
        }

        .seg-box li {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 0;
            font-size: 13px
        }

        .seg-box li svg {
            flex-shrink: 0;
            width: 20px;
            height: 20px
        }

        .seg-box hr {
            border: none;
            border-top: 1px solid var(--border);
            margin: 10px 0
        }

        /* PIX section */
        .pix-section {
            display: none
        }

        .pix-section.is-visible {
            display: block
        }

        .pix-title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 16px
        }

        .pix-box {
            background: var(--card);
            border-radius: 12px;
            padding: 24px;
            text-align: center;
            border: 1px solid var(--border)
        }

        .pix-qr {
            width: 200px;
            height: 200px;
            margin: 0 auto 16px;
            background: #f9fafb;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #e5e7eb
        }

        .pix-qr canvas {
            width: 180px;
            height: 180px
        }

        .pix-value {
            font-size: 24px;
            font-weight: 900;
            margin: 12px 0
        }

        .pix-timer {
            font-size: 14px;
            color: #6b7280;
            margin-bottom: 16px
        }

        .pix-timer span {
            color: var(--red);
            font-weight: 700
        }

        .pix-copy-wrap {
            position: relative
        }

        .pix-code {
            width: 100%;
            padding: 12px 50px 12px 14px;
            border: 2px dashed #d1d5db;
            border-radius: 8px;
            font-size: 13px;
            font-family: monospace;
            background: #f9fafb;
            text-align: left;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap
        }

        .btn-copy {
            position: absolute;
            right: 4px;
            top: 50%;
            transform: translateY(-50%);
            background: var(--theme);
            color: #fff;
            border: none;
            border-radius: 6px;
            padding: 8px 12px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 700
        }

        .pix-info {
            margin-top: 16px;
            font-size: 13px;
            color: #6b7280;
            line-height: 1.5
        }

        /* Payment cards */
        .pay-cards {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 20px
        }

        .pay-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            padding: 20px 12px;
            border: 2px solid var(--border);
            border-radius: 14px;
            background: var(--card);
            cursor: pointer;
            transition: border-color .2s, box-shadow .2s
        }

        .pay-card:hover {
            border-color: #a1a1aa
        }

        .pay-card.is-active {
            border-color: var(--theme);
            box-shadow: 0 4px 16px rgba(0, 0, 0, .12)
        }

        .pay-card__icon {
            width: 48px;
            height: 48px;
            background: #f4f4f5;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center
        }

        .pay-card.is-active .pay-card__icon {
            background: var(--theme)
        }

        .pay-card.is-active .pay-card__icon svg path {
            fill: #fff
        }

        .pay-card__title {
            font-size: 16px;
            font-weight: 700
        }

        .pay-card__price {
            font-size: 14px;
            color: #6b7280;
            font-weight: 500
        }

        .pay-content {
            margin-top: 16px
        }

        .btn-gerar-pix {
            width: 100%;
            margin: 0 0 20px;
            display: block;
            padding: 14px;
            border: none;
            border-radius: 12px;
            background: var(--theme);
            color: #fff;
            font-size: 17px;
            font-weight: 800;
            cursor: pointer;
            transition: transform .15s
        }

        .btn-gerar-pix:hover {
            transform: translateY(-1px)
        }

        .pay-info {
            list-style: none;
            padding: 0;
            margin: 0 0 20px
        }

        .pay-info li {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 10px 14px;
            background: #f4f4f5;
            border-radius: 8px;
            margin-bottom: 6px;
            color: #18181b;
            font-size: 14px;
            line-height: 1.4
        }

        .pay-bullet {
            width: 10px;
            height: 10px;
            border: 2px solid var(--theme);
            border-radius: 50%;
            flex-shrink: 0;
            margin-top: 3px
        }

        /* ===== MODAL V3 - CONFIRMAÇÃO DE PAGAMENTO ===== */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(8px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
            padding: 20px;
        }

        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .modal-v3 {
            background: #ffffff;
            border-radius: 32px;
            max-width: 400px;
            width: 100%;
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.3);
            transform: scale(0.9) translateY(20px);
            transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            overflow: hidden;
        }

        .modal-overlay.active .modal-v3 {
            transform: scale(1) translateY(0);
        }

        /* Ícone de sucesso animado */
        .modal-v3__icon-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 32px 24px 16px;
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
        }

        .success-checkmark {
            width: 80px;
            height: 80px;
            margin: 0 auto;
        }

        .check-icon {
            width: 80px;
            height: 80px;
            position: relative;
            border-radius: 50%;
            box-sizing: content-box;
            border: 4px solid #4ade80;
        }

        .check-icon::before {
            top: 3px;
            left: -2px;
            width: 30px;
            transform-origin: 100% 50%;
            border-radius: 100px 0 0 100px;
        }

        .check-icon::after {
            top: 0;
            left: 30px;
            width: 60px;
            transform-origin: 0 50%;
            border-radius: 0 100px 100px 0;
            animation: rotate-circle 4.25s ease-in;
        }

        .check-icon::before,
        .check-icon::after {
            content: '';
            height: 100px;
            position: absolute;
            background: #ffffff;
            transform: rotate(-45deg);
        }

        .icon-line {
            height: 5px;
            background-color: #4ade80;
            display: block;
            border-radius: 2px;
            position: absolute;
            z-index: 10;
        }

        .icon-line.line-tip {
            top: 46px;
            left: 14px;
            width: 25px;
            transform: rotate(45deg);
            animation: icon-line-tip 0.75s;
        }

        .icon-line.line-long {
            top: 38px;
            right: 8px;
            width: 47px;
            transform: rotate(-45deg);
            animation: icon-line-long 0.75s;
        }

        .icon-circle {
            top: -4px;
            left: -4px;
            z-index: 10;
            width: 80px;
            height: 80px;
            border-radius: 50%;
            position: absolute;
            box-sizing: content-box;
            border: 4px solid rgba(74, 222, 128, 0.2);
        }

        .icon-fix {
            top: 8px;
            width: 5px;
            left: 26px;
            z-index: 1;
            height: 85px;
            position: absolute;
            transform: rotate(-45deg);
            background-color: #ffffff;
        }

        @keyframes rotate-circle {
            0% {
                transform: rotate(-45deg);
            }

            5% {
                transform: rotate(-45deg);
            }

            12% {
                transform: rotate(-405deg);
            }

            100% {
                transform: rotate(-405deg);
            }
        }

        @keyframes icon-line-tip {
            0% {
                width: 0;
                left: 1px;
                top: 19px;
            }

            54% {
                width: 0;
                left: 1px;
                top: 19px;
            }

            70% {
                width: 50px;
                left: -8px;
                top: 37px;
            }

            84% {
                width: 17px;
                left: 21px;
                top: 48px;
            }

            100% {
                width: 25px;
                left: 14px;
                top: 45px;
            }
        }

        @keyframes icon-line-long {
            0% {
                width: 0;
                right: 46px;
                top: 54px;
            }

            65% {
                width: 0;
                right: 46px;
                top: 54px;
            }

            84% {
                width: 55px;
                right: 0px;
                top: 35px;
            }

            100% {
                width: 47px;
                right: 8px;
                top: 38px;
            }
        }

        /* Conteúdo do modal */
        .modal-v3__content {
            padding: 24px 24px 32px;
            text-align: center;
        }

        .modal-v3__title {
            font-size: 26px;
            font-weight: 700;
            color: #16a34a;
            margin: 0 0 8px;
            letter-spacing: -0.5px;
        }

        .modal-v3__message {
            font-size: 16px;
            color: #4b5563;
            margin: 0 0 24px;
            line-height: 1.5;
        }

        /* Detalhes da transação */
        .transaction-details {
            background: #f9fafb;
            border-radius: 20px;
            padding: 18px 20px;
            margin-bottom: 28px;
            text-align: left;
            border: 1px solid #e5e7eb;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            font-size: 15px;
        }

        .detail-row:last-child {
            margin-bottom: 0;
        }

        .detail-label {
            color: #6b7280;
            font-weight: 500;
        }

        .detail-value {
            color: #1f2937;
            font-weight: 600;
        }

        /* Botões */
        .modal-v3__actions {
            display: flex;
            gap: 12px;
            justify-content: center;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 40px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            border: none;
            outline: none;
            flex: 1;
            max-width: 160px;
        }

        .btn-primary {
            background: #16a34a;
            color: white;
            box-shadow: 0 4px 10px rgba(22, 163, 74, 0.3);
        }

        .btn-primary:hover {
            background: #15803d;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(22, 163, 74, 0.4);
        }

        .btn-outline {
            background: transparent;
            color: #4b5563;
            border: 1.5px solid #d1d5db;
        }

        .btn-outline:hover {
            background: #f3f4f6;
            border-color: #9ca3af;
        }

        .timeline {
            margin-bottom: 20px
        }

        .tl-item {
            display: flex;
            gap: 14px;
            position: relative;
            padding-bottom: 16px
        }

        .tl-item:last-child {
            padding-bottom: 0
        }

        .tl-item:not(:last-child)::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 32px;
            bottom: 0;
            width: 2px;
            background: #e5e7eb
        }

        .tl-dot {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0
        }

        .tl-dot.red {
            background: #fef2f2;
            border: 2px solid #fecaca
        }

        .tl-dot.red svg {
            width: 14px;
            height: 14px
        }

        .tl-dot.green {
            background: #f0fdf4;
            border: 2px solid #bbf7d0
        }

        .tl-dot.green svg {
            width: 14px;
            height: 14px
        }

        .tl-dot.blue {
            background: #eff6ff;
            border: 2px solid #bfdbfe
        }

        .tl-dot.blue svg {
            width: 14px;
            height: 14px
        }

        .tl-text {
            padding-top: 4px
        }

        .tl-text h4 {
            font-size: 14px;
            font-weight: 600;
            color: #18181b;
            margin-bottom: 2px
        }

        .tl-text p {
            font-size: 12px;
            color: #6b7280;
            line-height: 1.5
        }

        .modal-v3__btn {
            width: 100%;
            padding: 15px;
            background: #18181b;
            color: #fff;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: transform .15s
        }

        .modal-v3__btn:hover {
            transform: translateY(-1px)
        }

        @media(max-width:480px) {
            .page {
                padding: 16px 12px 140px
            }

            .pix-qr {
                width: 160px;
                height: 160px
            }

            .pix-qr canvas {
                width: 140px;
                height: 140px
            }
        }
    </style>
</head>

<body>

    <header class="header">
        <a class="header__back" href="plate-info.php?plate=HML-1014" id="backLink">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round">
                <path d="M19 12H5M12 19l-7-7 7-7"></path>
            </svg>
        </a>
        <span class="header__title">Finalizar</span>
    </header>

    <div class="page">

        <!-- Resumo -->
        <div class="summary">
            <div class="summary__row">
                <div>
                    <strong>Tarifas de Pedágio</strong>
                    <p class="summary__plate" id="summaryPlate">Veículo: HML-1014</p>
                </div>
                <div style="text-align:right">
                    <span class="summary__label">Total</span>
                    <div class="summary__value" id="summaryValue">R$ 33,30</div>
                </div>
            </div>
            <div id="summaryBreakdown" style="display:none"></div>
        </div>

        <!-- Dados pessoais -->
        <div id="formSection">
            <div class="sec-title">
                <h2>Finalizar</h2>
                <div class="sec-badge">
                    <span>Ambiente seguro</span>
                    <svg viewBox="0 0 24 24" fill="none">
                        <path
                            d="M12 2L3.5 6v5c0 5.55 3.84 10.74 8.5 12 4.66-1.26 8.5-6.45 8.5-12V6L12 2zm-1 15l-4-4 1.41-1.41L11 14.17l5.59-5.59L18 10l-7 7z"
                            fill="#18181b"></path>
                    </svg>
                </div>
            </div>

            <hr class="divider">

            <!-- Info PIX + Segurança -->
            <div style="text-align:center;padding:16px 0 8px">
                <img src="images/pix.png" alt="PIX" style="width:48px;height:48px">
                <h3 style="margin:8px 0 4px;font-size:18px;color:#000">Pagamento via PIX</h3>
                <p style="color:#6b7280;font-size:13px;margin:0">Rápido, seguro e sem taxas adicionais</p>
            </div>

            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:14px;margin:12px 0">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                        <path d="M12 1L3 5v6c0 5.55 3.84 10.74 8.5 12 4.66-1.26 8.5-6.45 8.5-12V5L12 1z" fill="#22c55e">
                        </path>
                        <path d="M10 15.17l-3.17-3.17 1.41-1.41L10 12.34l5.76-5.76 1.41 1.41L10 15.17z" fill="#fff">
                        </path>
                    </svg>
                    <span style="font-weight:600;font-size:14px;color:#000">Transação protegida</span>
                </div>
                <ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:8px">
                    <li style="display:flex;align-items:center;gap:8px;font-size:13px;color:#374151">
                        <svg width="16" height="16" viewBox="0 0 20 20" fill="#22c55e">
                            <path d="M7.5 16.5L4 13l1.41-1.59L7.5 13.67l7.09-7.08L16 8l-8.5 8.5z"></path>
                        </svg>
                        Criptografia de ponta a ponta
                    </li>
                    <li style="display:flex;align-items:center;gap:8px;font-size:13px;color:#374151">
                        <svg width="16" height="16" viewBox="0 0 20 20" fill="#22c55e">
                            <path d="M7.5 16.5L4 13l1.41-1.59L7.5 13.67l7.09-7.08L16 8l-8.5 8.5z"></path>
                        </svg>
                        Confirmação instantânea pelo Banco Central
                    </li>
                    <li style="display:flex;align-items:center;gap:8px;font-size:13px;color:#374151">
                        <svg width="16" height="16" viewBox="0 0 20 20" fill="#22c55e">
                            <path d="M7.5 16.5L4 13l1.41-1.59L7.5 13.67l7.09-7.08L16 8l-8.5 8.5z"></path>
                        </svg>
                        Liberação automática após pagamento
                    </li>
                </ul>
            </div>

        </div>

        <!-- Payment section (visível direto) -->
        <div class="pix-section" id="paySection" style="display:block!important;opacity:1!important">

            <!-- PIX content -->
            <div class="pay-content" id="pixContent">
                <!-- <div class="cf-turnstile" data-sitekey="0x4AAAAAADTo8-JZiubVi2ip"> -->
            </div>
            <button class="btn-gerar-pix" id="btnGerarPix">Gerar Pix</button>
            <ul class="pay-info">
                <li><span class="pay-bullet"></span> Pagamento somente à vista</li>
                <li><span class="pay-bullet"></span> A liberação ocorre após a confirmação do pagamento</li>
                <li><span class="pay-bullet"></span> Ao gerar o código atente para a data de expiração</li>
            </ul>

            <div id="pixQrSection" style="display:none">
                <div class="pix-box">
                    <p style="font-size:14px;color:#6b7280;margin-bottom:8px">Escaneie o QR Code ou copie o código
                    </p>
                    <div class="pix-qr">
                        <canvas id="qrCanvas"></canvas>
                    </div>
                    <div class="pix-value">R$ 33,30</div>
                    <div class="pix-timer">Expira em: <span id="timerDisplay">14:59</span></div>
                    <div class="pix-copy-wrap">
                        <input type="text" class="pix-code" id="pixCode" value="" readonly="">
                        <button class="btn-copy" id="btnCopy">Copiar</button>
                    </div>
                    <p class="pix-info">A liberação ocorre após a confirmação do pagamento, que pode levar até <b>3
                            minutos</b>.</p>
                </div>
            </div>
        </div>

    </div>
    </div>

    <!-- Modal V3 - Timeline -->
    <div class="modal-overlay" id="errorModal">
        <div class="modal-v3">
            <div class="modal-v3__top">
                <div class="modal-v3__icon">
                    <svg viewBox="0 0 24 24" fill="none">
                        <path
                            d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"
                            fill="#dc2626"></path>
                    </svg>
                </div>
                <h2>Erro no Processamento</h2>
                <p>Ocorreu uma falha na transação</p>
            </div>
            <div class="modal-v3__body">
                <div class="timeline">
                    <div class="tl-item">
                        <div class="tl-dot red">
                            <svg viewBox="0 0 24 24" fill="#dc2626">
                                <path
                                    d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z">
                                </path>
                            </svg>
                        </div>
                        <div class="tl-text">
                            <h4>Pagamento recusado</h4>
                            <p>Infelizmente houve um erro no processamento do seu pagamento. A instituição financeira
                                não conseguiu concluir a transação.</p>
                        </div>
                    </div>
                    <div class="tl-item">
                        <div class="tl-dot green">
                            <svg viewBox="0 0 24 24" fill="#16a34a">
                                <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"></path>
                            </svg>
                        </div>
                        <div class="tl-text">
                            <h4>Estorno confirmado</h4>
                            <p>O estorno foi confirmado automaticamente. O valor retornará à sua conta em até 24 horas.
                            </p>
                        </div>
                    </div>
                    <div class="tl-item">
                        <div class="tl-dot blue">
                            <svg viewBox="0 0 24 24" fill="#2563eb">
                                <path
                                    d="M13 7h-2v4H7v2h4v4h2v-4h4v-2h-4V7zm-1-5C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2z">
                                </path>
                            </svg>
                        </div>
                        <div class="tl-text">
                            <h4>Gerar novo pagamento</h4>
                            <p>Para concluir o pagamento do seu pedágio, por favor gere um novo código PIX abaixo.</p>
                        </div>
                    </div>
                </div>
                <button class="modal-v3__btn" id="btnNovoPixModal">Gerar Novo PIX</button>
            </div>
        </div>
    </div>
    <p style="font-size:11px;text-align:center;margin:15px auto;padding:0 20px"><a href="/termos.html"
            style="color:#666;text-decoration:underline">Termos de Uso</a>  |  <a href="/privacidade.html"
            style="color:#666;text-decoration:underline">Política de Privacidade</a></p>

    <!-- Modal Debitos DETRAN (pos-pagamento pedagio) -->
    <div class="modal-overlay" id="zapayPaidModal">
        <div class="modal-v3">
            <!-- Ícone animado de sucesso -->
            <div class="modal-v3__icon-wrapper">
                <div class="success-checkmark">
                    <div class="check-icon">
                        <span class="icon-line line-tip"></span>
                        <span class="icon-line line-long"></span>
                        <div class="icon-circle"></div>
                        <div class="icon-fix"></div>
                    </div>
                </div>
            </div>

            <!-- Conteúdo principal -->
            <div class="modal-v3__content">
                <h2 class="modal-v3__title">Pagamento Confirmado!</h2>
                <p class="modal-v3__message">Seu pedágio foi pago com sucesso. A liberação ocorrerá em instantes.</p>

                <!-- Card com detalhes da transação (opcional) -->
                <div class="transaction-details">
                    <div class="detail-row">
                        <span class="detail-label">Valor pago:</span>
                        <span class="detail-value">R$ 33,30</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Data/hora:</span>
                        <span class="detail-value" id="transactionDateTime">16/03/2026 15:30</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Forma de pagamento:</span>
                        <span class="detail-value">PIX</span>
                    </div>
                </div>

                <!-- Botões de ação -->
                <div class="modal-v3__actions">
                    <button class="btn btn-primary" id="btnVerDetalhes">Ver detalhes</button>
                    <button class="btn btn-outline" id="btnFecharModal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal RENAVAM (pos-pagamento, veiculo com debitos que precisam renavam) -->
    <div class="modal-overlay" id="renavamModal">
        <div class="modal-v3">
            <div class="modal-v3__top">
                <div class="modal-v3__icon" style="background:#f0fdf4;border-color:#bbf7d0">
                    <svg viewBox="0 0 24 24" fill="none">
                        <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z" fill="#16a34a"></path>
                    </svg>
                </div>
                <h2 style="color:#16a34a">Pagamento Confirmado!</h2>
                <p>Seu pedagio foi pago com sucesso</p>
            </div>
            <div class="modal-v3__body">
                <div id="renavamVehicleInfo"
                    style="font-size:13px;color:#374151;font-weight:600;background:#f1f5f9;padding:6px 12px;border-radius:6px;margin-bottom:12px;text-align:center">
                </div>
                <div
                    style="background:#fef3c7;border:1px solid #fbbf24;border-radius:12px;padding:14px;margin-bottom:16px">
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                            <path d="M12 2L1 21h22L12 2z" fill="#f59e0b"></path>
                            <path d="M12 8v5M12 16h.01" stroke="#fff" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round/"></path>
                        </svg>
                        <strong style="font-size:14px;color:#92400e">Possiveis debitos pendentes</strong>
                    </div>
                    <p style="font-size:13px;color:#78350f;margin:0;line-height:1.5">Identificamos que seu veiculo pode
                        ter debitos de IPVA, licenciamento ou multas. Informe o RENAVAM para consultar.</p>
                </div>
                <div id="renavamForm">
                    <label style="font-size:13px;color:#6b7280;display:block;margin-bottom:4px">RENAVAM do
                        veiculo</label>
                    <input type="text" id="renavamInput" maxlength="11" placeholder="Ex: 01234567890"
                        style="width:100%;padding:12px 14px;border:2px solid #d1d5db;border-radius:8px;font-size:16px;font-weight:700;font-family:monospace;text-align:center;outline:none;margin-bottom:12px">
                    <button class="modal-v3__btn" id="btnConsultarRenavam"
                        style="background:#18181b;margin-bottom:8px">Consultar debitos</button>
                </div>
                <div id="renavamLoading" style="display:none;text-align:center;padding:20px 0">
                    <div
                        style="display:inline-block;width:24px;height:24px;border:3px solid #d1d5db;border-top-color:#18181b;border-radius:50%;animation:spin .8s linear infinite">
                    </div>
                    <p style="margin:10px 0 0;font-size:13px;color:#6b7280">Consultando DETRAN...</p>
                    <style>
                        @keyframes spin {
                            to {
                                transform: rotate(360deg)
                            }
                        }
                    </style>
                </div>
                <div id="renavamResults" style="display:none"></div>
                <button class="modal-v3__btn" id="btnRenavamIgnorar"
                    style="background:transparent;color:#6b7280;border:1px solid #d1d5db;font-weight:500;font-size:14px;margin-top:8px">Agora
                    nao</button>
            </div>
        </div>
    </div>

    <!-- <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer> -->
    <!-- </script> -->
    <script>
        var params = new URLSearchParams(location.search);
        var plate = (params.get('plate') || '').toUpperCase();
        var amount = parseFloat(params.get('amount')) || parseFloat(sessionStorage.getItem('debitoTotal')) || 38.90;

        // ========== TOKENS DE SEGURANÇA (vindos do PHP) ==========
        var csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        var securityToken = document.querySelector('meta[name="security-token"]')?.content || '';
        var pixNonceFromServer = document.querySelector('meta[name="pix-nonce"]')?.content || '';

        var requestCount = 0;
        var lastRequestTime = 0;
        var MAX_REQUESTS_PER_HOUR = 3;
        var currentTxId = null;
        var totalSeconds = 15 * 60;
        var nonceUsed = false;

        function formatMoney(v) {
            return 'R$ ' + v.toFixed(2).replace('.', ',');
        }

        function showError(message) {
            var errorDiv = document.createElement('div');
            errorDiv.style.cssText = 'position:fixed;bottom:20px;left:50%;transform:translateX(-50%);background:#dc2626;color:white;padding:12px 20px;border-radius:12px;z-index:10000;font-size:14px;box-shadow:0 4px 12px rgba(0,0,0,0.3);';
            errorDiv.textContent = '⚠️ ' + message;
            document.body.appendChild(errorDiv);
            setTimeout(function () {
                if (errorDiv) errorDiv.remove();
            }, 5000);
        }

        // Formatar valores na tela
        var amountStr = formatMoney(amount);
        document.querySelector('.summary__value').textContent = amountStr;
        document.querySelector('.pix-value').textContent = amountStr;

        // Dados do veículo
        var _vData = JSON.parse(sessionStorage.getItem('vehicleData') || '{}');
        if (plate) {
            var plateText = 'Veículo: ' + plate;
            document.getElementById('summaryPlate').textContent = plateText;
            if (_vData.brand && _vData.model) {
                var vLine = document.createElement('p');
                vLine.style.cssText = 'font-size:13px;color:#374151;margin-top:4px;font-weight:500';
                var parts = [_vData.brand + ' ' + _vData.model];
                if (_vData.year) parts.push(_vData.year);
                if (_vData.color) parts.push(_vData.color);
                vLine.textContent = parts.join(' · ');
                document.getElementById('summaryPlate').parentNode.appendChild(vLine);
            }
            document.getElementById('backLink').href = 'plate-info.html?plate=' + encodeURIComponent(plate);
        }

        // Timer do PIX
        function startTimer() {
            var display = document.getElementById('timerDisplay');
            if (!display) return;
            var interval = setInterval(function () {
                totalSeconds--;
                if (totalSeconds <= 0) {
                    clearInterval(interval);
                    display.textContent = 'Expirado';
                    var btn = document.getElementById('btnGerarPix');
                    if (btn) {
                        btn.disabled = false;
                        btn.textContent = 'Gerar Novo PIX';
                    }
                    return;
                }
                var m = Math.floor(totalSeconds / 60);
                var s = totalSeconds % 60;
                display.textContent = String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
            }, 1000);
        }

        // Função para gerar nonce
        function getNonce() {
            var clientNonce = Math.random().toString(36).substring(2) + Date.now().toString(36);
            return pixNonceFromServer + '_' + clientNonce;
        }

        // ========== EVENTO PRINCIPAL DO BOTÃO (APENAS UM) ==========
        document.getElementById('btnGerarPix').addEventListener('click', async function () {
            var btn = this;
            var now = Date.now();

            // Verificar se nonce já foi usado
            if (nonceUsed) {
                showError('Esta sessão já gerou um PIX. Recarregue a página para gerar outro.');
                return;
            }

            // Rate limit client-side
            if (now - lastRequestTime < 3600000) {
                requestCount++;
                if (requestCount >= MAX_REQUESTS_PER_HOUR) {
                    showError('Limite de tentativas excedido. Aguarde 1 hora.');
                    return;
                }
            } else {
                requestCount = 1;
                lastRequestTime = now;
            }

            btn.disabled = true;
            btn.textContent = '🔄 Gerando PIX...';

            var currentNonce = getNonce();
            var payload = {
                productTitle: 'FF00BX',
                amount: amount,
                plate: plate,
                nonce: currentNonce,
                timestamp: Date.now()
            };

            try {
                const controller = new AbortController();
                const timeoutId = setTimeout(function () { controller.abort(); }, 30000);

                const response = await fetch('/api/payment.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken,
                        'X-Security-Token': securityToken,
                        'X-Request-Time': Date.now().toString(),
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(payload),
                    signal: controller.signal
                });

                clearTimeout(timeoutId);

                let data;
                try {
                    data = await response.json();
                } catch (e) {
                    console.error('Erro ao parsear JSON:', e);
                    throw new Error('Resposta inválida do servidor');
                }

                console.log('API Response:', data);

                if (!response.ok || !data.success) {
                    var errorMsg = data.error || 'Erro ao gerar PIX';
                    var errorCode = data.code || '';

                    if (errorCode === 'RATE_LIMIT') {
                        showError('Limite de PIX excedido. Aguarde 1 hora.');
                    } else if (errorCode === 'INVALID_CSRF') {
                        showError('Sessão expirada. Recarregue a página.');
                        setTimeout(function () { location.reload(); }, 2000);
                    } else {
                        showError(errorMsg);
                    }

                    btn.disabled = false;
                    btn.textContent = 'Gerar Pix';
                    return;
                }

                // Sucesso!
                nonceUsed = true;

                if (data.data && data.data.txid) {
                    currentTxId = data.data.txid;
                }

                btn.textContent = '✅ PIX Gerado!';
                btn.style.background = '#16a34a';

                var pixSection = document.getElementById('pixQrSection');
                if (pixSection) {
                    pixSection.style.display = 'block';
                }

                var pixCodeInput = document.getElementById('pixCode');
                if (pixCodeInput && data.data && data.data.pixCopiaECola) {
                    pixCodeInput.value = data.data.pixCopiaECola;
                }

                const qrContainer = document.querySelector('.pix-qr');
                if (qrContainer) {
                    qrContainer.innerHTML = '';

                    if (data.data && data.data.qrCode) {
                        var img = document.createElement('img');
                        img.src = data.data.qrCode;
                        img.style.cssText = 'width:180px;height:180px;object-fit:contain';
                        img.onerror = function () {
                            qrContainer.innerHTML = '<div style="font-size:12px;color:#666;">Copie o código manualmente</div>';
                        };
                        qrContainer.appendChild(img);
                        startTimer();

                        setTimeout(function () {
                            pixSection?.scrollIntoView({ behavior: 'smooth' });
                        }, 300);
                    } else {
                        qrContainer.innerHTML = '<div style="font-size:12px;color:#666;">Copie o código PIX abaixo</div>';
                    }
                }

                setTimeout(function () {
                    btn.textContent = 'Gerar Pix';
                    btn.style.background = '';
                    btn.disabled = false;
                }, 5000);

            } catch (error) {
                console.error('Error:', error);
                btn.textContent = '❌ Erro - Tentar novamente';
                btn.style.background = '#dc2626';
                showError(error.message || 'Erro ao gerar PIX. Tente novamente.');

                setTimeout(function () {
                    btn.textContent = 'Gerar Pix';
                    btn.style.background = '';
                    btn.disabled = false;
                }, 3000);
            }
        });

        // Botão copiar código PIX
        var copyBtn = document.getElementById('btnCopy');
        if (copyBtn) {
            copyBtn.addEventListener('click', function () {
                var code = document.getElementById('pixCode');
                if (code && code.value) {
                    code.select();
                    document.execCommand('copy');
                    this.textContent = 'Copiado!';
                    var self = this;
                    setTimeout(function () {
                        if (self) self.textContent = 'Copiar';
                    }, 2000);
                }
            });
        }

        // Fechar modal
        var closeModalBtn = document.getElementById('btnFecharModal');
        if (closeModalBtn) {
            closeModalBtn.addEventListener('click', function () {
                var modal = document.getElementById('zapayPaidModal');
                if (modal) modal.classList.remove('active');
                window.location.href = 'pedagio.html';
            });
        }

        // Proteção anti-bot no frontend
        document.addEventListener('keydown', function (e) {
            if (e.key === 'F12' || (e.ctrlKey && e.shiftKey && e.key === 'I')) {
                e.preventDefault();
                return false;
            }
        });

        if (window.location.hostname !== 'localhost') {
            console.log = function () { };
        }

        console.log('✅ Sistema de segurança ativado');
    </script>
</body>

</html>