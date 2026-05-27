<?php
// secure_admin_2026_login.php
require_once '../config.php';

$security = new Security();
$error    = '';

// Se já estiver logado, vai direto para o admin
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: secure_admin_2026.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Preencha usuário e senha.';
    } else {
        if ($username === 'admin' && $password === 'admin123') {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username']  = $username;
            $_SESSION['admin_role']      = 'admin';
            $_SESSION['last_activity']   = time();
            header('Location: secure_admin_2026.php');
            exit;
        } else {
            $error = 'Usuário ou senha inválidos.';
            $security->recordLoginAttempt($username, false);
        }
    }
}

$has_error = !empty($error);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Acesso Seguro — Toll System Admin 2026</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
/* ── Reset & Tokens ─────────────────────────────────────────────────────── */
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}

:root{
  --bg:       #0a0c14;
  --surface:  #111422;
  --surface2: #171b2e;
  --border:   rgba(255,255,255,.07);
  --text:     #e2e8f0;
  --muted:    #64748b;
  --accent:   #e5ff51;
  --green:    #22c55e;
  --red:      #ef4444;
  --cyan:     #06b6d4;
  --font:     'Inter', system-ui, sans-serif;
  --mono:     'JetBrains Mono', monospace;
}

html,body{height:100%;overflow:hidden}

body{
  font-family:var(--font);
  background:var(--bg);
  color:var(--text);
  display:flex;
  min-height:100vh;
}

/* ── Animated grid background ───────────────────────────────────────────── */
body::before{
  content:'';
  position:fixed;inset:0;
  background-image:
    linear-gradient(rgba(229,255,81,.03) 1px,transparent 1px),
    linear-gradient(90deg,rgba(229,255,81,.03) 1px,transparent 1px);
  background-size:48px 48px;
  animation:grid-drift 20s linear infinite;
  pointer-events:none;z-index:0;
}
@keyframes grid-drift{0%{background-position:0 0}100%{background-position:48px 48px}}

/* ── Glow blobs ─────────────────────────────────────────────────────────── */
.blob{
  position:fixed;border-radius:50%;filter:blur(80px);
  pointer-events:none;z-index:0;opacity:.18;
  animation:blob-float 10s ease-in-out infinite;
}
.blob-1{width:500px;height:500px;background:radial-gradient(circle,#e5ff51,transparent);top:-160px;left:-160px}
.blob-2{width:400px;height:400px;background:radial-gradient(circle,#7c3aed,transparent);bottom:-120px;right:-80px;animation-delay:-4s}
.blob-3{width:260px;height:260px;background:radial-gradient(circle,#06b6d4,transparent);bottom:30%;left:30%;animation-delay:-7s;opacity:.1}
@keyframes blob-float{0%,100%{transform:translate(0,0) scale(1)}50%{transform:translate(20px,-20px) scale(1.04)}}

/* ── Two-panel layout ───────────────────────────────────────────────────── */
.page{
  position:relative;z-index:1;
  display:flex;width:100%;min-height:100vh;
}

/* LEFT — Branding ─────────────────────────────────────────────────────── */
.panel-brand{
  flex:1;
  display:flex;flex-direction:column;justify-content:center;
  padding:60px 64px;
  position:relative;overflow:hidden;
}

.panel-brand::after{
  content:'';
  position:absolute;top:0;right:0;bottom:0;width:1px;
  background:linear-gradient(to bottom,transparent,rgba(229,255,81,.25) 30%,rgba(229,255,81,.25) 70%,transparent);
}

.brand-top{margin-bottom:48px}

.brand-logo{
  display:inline-flex;align-items:center;gap:12px;
  background:rgba(229,255,81,.07);
  border:1px solid rgba(229,255,81,.18);
  border-radius:12px;padding:12px 18px;
  margin-bottom:40px;
}
.brand-logo-icon{
  width:38px;height:38px;border-radius:9px;
  background:linear-gradient(135deg,var(--accent),#a3e635);
  display:flex;align-items:center;justify-content:center;
  font-size:20px;flex-shrink:0;
}
.brand-logo-text{
  display:flex;flex-direction:column;line-height:1.2;
}
.brand-logo-title{font-size:14px;font-weight:700;color:var(--accent)}
.brand-logo-sub{font-size:11px;color:var(--muted);font-weight:500;letter-spacing:.5px;text-transform:uppercase}

.brand-headline{
  font-size:clamp(28px,3.5vw,44px);
  font-weight:800;
  line-height:1.15;
  letter-spacing:-1.2px;
  color:var(--text);
  margin-bottom:16px;
}
.brand-headline em{
  font-style:normal;
  background:linear-gradient(135deg,var(--accent),#a3e635);
  -webkit-background-clip:text;-webkit-text-fill-color:transparent;
  background-clip:text;
}

.brand-desc{
  font-size:15px;color:var(--muted);line-height:1.7;
  max-width:380px;margin-bottom:44px;
}

/* Feature list */
.features{display:flex;flex-direction:column;gap:14px}
.feature-item{
  display:flex;align-items:center;gap:12px;
  background:rgba(255,255,255,.03);
  border:1px solid var(--border);
  border-radius:10px;padding:12px 16px;
  transition:background .2s,border-color .2s;
}
.feature-item:hover{background:rgba(255,255,255,.05);border-color:rgba(229,255,81,.15)}
.feature-icon{
  width:34px;height:34px;border-radius:8px;
  display:flex;align-items:center;justify-content:center;
  font-size:16px;flex-shrink:0;
}
.fi-green{background:rgba(34,197,94,.1)}
.fi-cyan{background:rgba(6,182,212,.1)}
.fi-accent{background:rgba(229,255,81,.08)}
.fi-purple{background:rgba(124,58,237,.1)}

.feature-body{}
.feature-title{font-size:13px;font-weight:600;color:var(--text)}
.feature-desc{font-size:12px;color:var(--muted);margin-top:1px}

/* Security status bar */
.status-bar{
  margin-top:48px;
  display:flex;align-items:center;gap:10px;
  padding:12px 16px;
  background:rgba(34,197,94,.06);
  border:1px solid rgba(34,197,94,.2);
  border-radius:10px;
}
.status-dot{
  width:9px;height:9px;border-radius:50%;
  background:var(--green);flex-shrink:0;
  box-shadow:0 0 8px var(--green);
  animation:pulse-g 2s ease-in-out infinite;
}
@keyframes pulse-g{0%,100%{opacity:1;box-shadow:0 0 8px var(--green)}50%{opacity:.5;box-shadow:0 0 4px var(--green)}}
.status-text{font-size:12.5px;color:var(--green);font-weight:600}
.status-time{margin-left:auto;font-family:var(--mono);font-size:11px;color:var(--muted)}

/* RIGHT — Login form ──────────────────────────────────────────────────── */
.panel-login{
  width:min(460px,100%);
  display:flex;align-items:center;justify-content:center;
  padding:40px 32px;
  background:rgba(17,20,34,.6);
  backdrop-filter:blur(16px);
  position:relative;
}

.login-card{
  width:100%;max-width:380px;
}

/* Card header */
.card-header{
  text-align:center;
  margin-bottom:36px;
}
.card-shield{
  width:64px;height:64px;
  background:linear-gradient(135deg,rgba(229,255,81,.12),rgba(229,255,81,.04));
  border:1px solid rgba(229,255,81,.25);
  border-radius:18px;
  display:flex;align-items:center;justify-content:center;
  font-size:28px;
  margin:0 auto 18px;
  position:relative;
}
.card-shield::before{
  content:'';position:absolute;inset:-1px;
  border-radius:18px;
  background:linear-gradient(135deg,rgba(229,255,81,.3),transparent,rgba(124,58,237,.2));
  z-index:-1;
}
.card-title{
  font-size:22px;font-weight:800;color:var(--text);
  letter-spacing:-.5px;margin-bottom:5px;
}
.card-subtitle{font-size:13.5px;color:var(--muted)}

/* Error alert */
.alert-error{
  display:flex;align-items:flex-start;gap:10px;
  background:rgba(239,68,68,.1);
  border:1px solid rgba(239,68,68,.3);
  border-radius:10px;padding:13px 14px;
  margin-bottom:22px;
  animation:shake .4s ease;
}
@keyframes shake{
  0%,100%{transform:translateX(0)}
  20%{transform:translateX(-6px)}
  40%{transform:translateX(6px)}
  60%{transform:translateX(-4px)}
  80%{transform:translateX(4px)}
}
.alert-icon{font-size:17px;flex-shrink:0;margin-top:1px}
.alert-msg{font-size:13.5px;color:#fca5a5;font-weight:500}

/* Form */
.form-group{margin-bottom:18px;position:relative}

.form-label{
  display:block;font-size:12.5px;font-weight:600;
  color:var(--muted);letter-spacing:.3px;
  text-transform:uppercase;margin-bottom:8px;
}

.input-wrap{position:relative}

.form-input{
  width:100%;
  background:var(--surface2);
  border:1px solid var(--border);
  color:var(--text);
  font-family:var(--font);font-size:14px;
  padding:13px 16px;
  border-radius:10px;
  transition:border-color .2s,box-shadow .2s;
  outline:none;
}
.form-input::placeholder{color:var(--muted);font-size:13px}
.form-input:focus{
  border-color:rgba(229,255,81,.5);
  box-shadow:0 0 0 3px rgba(229,255,81,.08),0 0 0 1px rgba(229,255,81,.2);
}
.form-input.has-icon{padding-left:42px}
.form-input.has-action{padding-right:42px}

<?php if($has_error): ?>
.form-input{border-color:rgba(239,68,68,.4)!important}
<?php endif ?>

.input-prefix{
  position:absolute;left:13px;top:50%;transform:translateY(-50%);
  font-size:16px;pointer-events:none;opacity:.5;
}
.input-action{
  position:absolute;right:11px;top:50%;transform:translateY(-50%);
  background:none;border:none;cursor:pointer;
  color:var(--muted);font-size:15px;padding:4px;border-radius:5px;
  transition:color .18s;
}
.input-action:hover{color:var(--text)}

/* Submit btn */
.btn-submit{
  width:100%;
  padding:14px;border-radius:11px;border:none;
  background:var(--accent);
  color:#0a0c14;
  font-family:var(--font);font-size:14.5px;font-weight:700;
  cursor:pointer;
  transition:all .2s ease;
  display:flex;align-items:center;justify-content:center;gap:8px;
  letter-spacing:.2px;
  position:relative;overflow:hidden;
  margin-top:8px;
}
.btn-submit::before{
  content:'';position:absolute;inset:0;
  background:linear-gradient(135deg,rgba(255,255,255,.15),transparent);
  opacity:0;transition:opacity .2s;
}
.btn-submit:hover{
  transform:translateY(-1px);
  box-shadow:0 8px 24px rgba(229,255,81,.35);
}
.btn-submit:hover::before{opacity:1}
.btn-submit:active{transform:translateY(0);box-shadow:none}
.btn-submit.loading{opacity:.7;pointer-events:none}
.btn-submit .spinner{
  width:16px;height:16px;border:2px solid rgba(0,0,0,.2);
  border-top-color:#0a0c14;border-radius:50%;
  animation:spin .7s linear infinite;display:none;
}
@keyframes spin{to{transform:rotate(360deg)}}

/* Divider */
.divider{
  display:flex;align-items:center;gap:12px;
  margin:24px 0 20px;color:var(--muted);font-size:11.5px;
}
.divider::before,.divider::after{content:'';flex:1;height:1px;background:var(--border)}

/* Info hint */
.hint-box{
  background:rgba(6,182,212,.07);
  border:1px solid rgba(6,182,212,.18);
  border-radius:10px;
  padding:13px 16px;
  display:flex;align-items:center;gap:10px;
}
.hint-icon{font-size:15px;flex-shrink:0}
.hint-text{font-size:12px;color:#67e8f9;line-height:1.5}
.hint-text strong{font-weight:600}

/* Footer */
.card-footer{
  margin-top:28px;text-align:center;
  font-size:11.5px;color:var(--muted);
  display:flex;align-items:center;justify-content:center;gap:6px;
}
.card-footer::before{content:'🔒'}

/* Security badges row */
.badges-row{
  display:flex;gap:8px;justify-content:center;
  margin-top:16px;flex-wrap:wrap;
}
.sec-badge{
  display:inline-flex;align-items:center;gap:5px;
  background:rgba(255,255,255,.04);
  border:1px solid var(--border);
  border-radius:20px;padding:4px 10px;
  font-size:10.5px;color:var(--muted);
}

/* ── Responsive ─────────────────────────────────────────────────────────── */
@media(max-width:900px){
  .panel-brand{display:none}
  .panel-login{width:100%}
}
@media(max-width:480px){
  .panel-login{padding:24px 20px}
}
</style>
</head>
<body>

<!-- Background effects -->
<div class="blob blob-1"></div>
<div class="blob blob-2"></div>
<div class="blob blob-3"></div>

<div class="page">

  <!-- ══ LEFT — Branding ══════════════════════════════════════════════════ -->
  <div class="panel-brand">

    <div class="brand-top">
      <div class="brand-logo">
        <div class="brand-logo-icon">🛡</div>
        <div class="brand-logo-text">
          <span class="brand-logo-title">Toll System</span>
          <span class="brand-logo-sub">Admin · 2026</span>
        </div>
      </div>

      <h1 class="brand-headline">
        Painel de<br>controle <em>seguro</em><br>e inteligente.
      </h1>
      <p class="brand-desc">
        Gerencie veículos, transações e monitore atividades em tempo real com segurança de nível corporativo.
      </p>
    </div>

    <div class="features">
      <div class="feature-item">
        <div class="feature-icon fi-green">🔐</div>
        <div class="feature-body">
          <div class="feature-title">Autenticação segura</div>
          <div class="feature-desc">Controle de sessão com timeout automático</div>
        </div>
      </div>
      <div class="feature-item">
        <div class="feature-icon fi-cyan">📡</div>
        <div class="feature-body">
          <div class="feature-title">Monitoramento em tempo real</div>
          <div class="feature-desc">Eventos de segurança e rate limits ao vivo</div>
        </div>
      </div>
      <div class="feature-item">
        <div class="feature-icon fi-accent">🚫</div>
        <div class="feature-body">
          <div class="feature-title">Bloqueio de IPs</div>
          <div class="feature-desc">Proteja o sistema contra acessos maliciosos</div>
        </div>
      </div>
      <div class="feature-item">
        <div class="feature-icon fi-purple">💰</div>
        <div class="feature-body">
          <div class="feature-title">Gestão de pagamentos PIX</div>
          <div class="feature-desc">Estatísticas e histórico de transações</div>
        </div>
      </div>
    </div>

    <div class="status-bar">
      <div class="status-dot"></div>
      <span class="status-text">Todos os sistemas operacionais</span>
      <span class="status-time" id="sb-clock">--:--:--</span>
    </div>

  </div><!-- /panel-brand -->

  <!-- ══ RIGHT — Login form ════════════════════════════════════════════════ -->
  <div class="panel-login">
    <div class="login-card">

      <!-- Card header -->
      <div class="card-header">
        <div class="card-shield">🔒</div>
        <div class="card-title">Acesso Restrito</div>
        <div class="card-subtitle">Entre com suas credenciais de administrador</div>
      </div>

      <!-- Error alert -->
      <?php if ($has_error): ?>
      <div class="alert-error" id="alertBox">
        <span class="alert-icon">⛔</span>
        <span class="alert-msg"><?php echo htmlspecialchars($error) ?></span>
      </div>
      <?php endif ?>

      <!-- Form -->
      <form method="POST" action="" id="loginForm" autocomplete="off">

        <div class="form-group">
          <label class="form-label" for="username">Usuário</label>
          <div class="input-wrap">
            <span class="input-prefix">👤</span>
            <input
              class="form-input has-icon"
              type="text"
              id="username"
              name="username"
              placeholder="Digite seu usuário"
              required
              autofocus
              autocomplete="username"
              value="<?php echo $has_error ? htmlspecialchars($_POST['username'] ?? '') : '' ?>"
            >
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="password">Senha</label>
          <div class="input-wrap">
            <span class="input-prefix">🔑</span>
            <input
              class="form-input has-icon has-action"
              type="password"
              id="password"
              name="password"
              placeholder="Digite sua senha"
              required
              autocomplete="current-password"
            >
            <button type="button" class="input-action" id="togglePwd" title="Mostrar/ocultar senha" aria-label="Mostrar senha">
              👁
            </button>
          </div>
        </div>

        <button type="submit" class="btn-submit" id="submitBtn">
          <span class="btn-spinner spinner" id="spinner"></span>
          <span id="btnText">Entrar no painel</span>
          <span>→</span>
        </button>

      </form>

      <div class="divider">acesso seguro</div>

      <!-- Hint -->
      <!-- <div class="hint-box">
        <span class="hint-icon">ℹ️</span>
        <div class="hint-text">
          Credenciais padrão: <strong>admin</strong> / <strong>admin123</strong><br>
          Altere após o primeiro acesso.
        </div>
      </div> -->

      <!-- Footer -->
      <div class="card-footer">
        Área protegida · Toll System Admin 2026
      </div>

      <div class="badges-row">
        <span class="sec-badge">🔒 SSL</span>
        <span class="sec-badge">🛡 Rate limit</span>
        <span class="sec-badge">📋 Auditoria</span>
        <span class="sec-badge">⏱ Session timeout</span>
      </div>

    </div>
  </div><!-- /panel-login -->

</div><!-- /page -->

<script>
// ── Clock ──────────────────────────────────────────────────────────────────
function tick(){
  const t=new Date().toLocaleTimeString('pt-BR',{hour:'2-digit',minute:'2-digit',second:'2-digit'});
  const el=document.getElementById('sb-clock');
  if(el) el.textContent=t;
}
tick();setInterval(tick,1000);

// ── Toggle password visibility ─────────────────────────────────────────────
document.getElementById('togglePwd').addEventListener('click',function(){
  const inp=document.getElementById('password');
  const isText=inp.type==='text';
  inp.type=isText?'password':'text';
  this.textContent=isText?'👁':'🙈';
});

// ── Loading state on submit ────────────────────────────────────────────────
document.getElementById('loginForm').addEventListener('submit',function(){
  const btn=document.getElementById('submitBtn');
  const txt=document.getElementById('btnText');
  const sp=document.getElementById('spinner');
  btn.classList.add('loading');
  txt.textContent='Verificando…';
  sp.style.display='block';
});

// ── Input focus glow ───────────────────────────────────────────────────────
document.querySelectorAll('.form-input').forEach(el=>{
  el.addEventListener('focus',function(){this.style.background='rgba(23,27,46,.9)'});
  el.addEventListener('blur', function(){this.style.background=''});
});

// ── Auto-dismiss error after 5s ────────────────────────────────────────────
const alertBox=document.getElementById('alertBox');
if(alertBox){
  setTimeout(()=>{
    alertBox.style.transition='opacity .5s ease,transform .5s ease';
    alertBox.style.opacity='0';alertBox.style.transform='translateY(-6px)';
    setTimeout(()=>alertBox.remove(),500);
  },5000);
}
</script>
</body>
</html>
