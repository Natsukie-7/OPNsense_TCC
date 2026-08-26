<!-- Sistema de abas (Tabs) padrão do OPNsense -->
<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#general">General Settings</a></li>
</ul>

<div class="tab-content content-box">
    <div id="general" class="tab-pane fade in active">
        <div class="content-box-main" style="padding: 20px;">
            <h1>Bem-vindo ao User Register!</h1>
            <p>Plugin está funcionando. Preparando modulo para TCC.</p>
            <button class="btn btn-primary" id="btnTestApi">Testar API</button>
        </div>
    </div>
</div>

<!-- Mude apenas a linha do ajaxCall no seu script existente -->
<script>
    $(document).ready(function() {
        $("#btnTestApi").click(function() {
            // Ajustado para api/user_register/...
            ajaxCall("/api/user_register/settings/get", {}, function(data, status) {
                alert("Resposta da API: " + data.message);
            });
        });
    });
</script>