<link rel="stylesheet" href="/css/userregister.css">
<div id="userregister" class="content-box">
    <header class="ur-header">
        <span class="ur-eyebrow">USER REGISTER · OPNsense</span>
        <h1><i class="fa fa-graduation-cap" aria-hidden="true"></i> Cadastro de alunos</h1>
        <p>Da planilha às contas de acesso, com conferência antes de cadastrar.</p>
        <div class="ur-steps"><span>1. Enviar planilha</span><span>2. Conferir dados</span><span>3. Criar e baixar acessos</span></div>
    </header>
    <div class="ur-body">
        <div id="ur-message" class="alert hidden" role="status" aria-live="polite"></div>
        <form id="ur-upload" class="ur-upload">
            <div class="form-group">
                <label for="ur-file">Planilha de alunos matriculados (.xlsx)</label>
                <input id="ur-file" class="form-control" type="file" accept=".xlsx" required>
                <p class="help-block">Coluna A: matrícula/RA · Coluna B: nome completo · Uma aba por curso.<br>
                    Com ou sem cabeçalho. Todas as abas serão processadas. Até 5 MiB / 5.000 linhas.</p>
            </div>
            <div class="form-group">
                <label for="ur-expiry">Validade das novas contas (opcional)</label>
                <input id="ur-expiry" class="form-control" type="date">
                <p class="help-block">Em branco: sem expiração automática.</p>
            </div>
            <button id="ur-preview" class="btn btn-primary" type="submit"><i class="fa fa-search" aria-hidden="true"></i> Analisar planilha</button>
        </form>
        <div class="alert alert-info ur-notice">
            O login será a matrícula, com senha aleatória individual. As contas não recebem acesso administrativo,
            shell ou grupos privilegiados. Usuários existentes nunca são alterados. Para acesso à rede,
            configure o Portal Cativo com autenticação <strong>Local Database</strong>.
        </div>
        <section id="ur-review" class="hidden" aria-label="Conferência de importação">
            <div class="ur-stats">
                <div><strong id="ur-total">0</strong><span>Linhas de alunos</span></div>
                <div><strong id="ur-new">0</strong><span>Novas contas</span></div>
                <div><strong id="ur-existing">0</strong><span>Existentes / repetidas</span></div>
                <div><strong id="ur-errors">0</strong><span>Precisam de correção</span></div>
            </div>
            <p id="ur-details" class="text-muted"></p>
            <div class="ur-toolbar">
                <label for="ur-filter">Filtrar visualização</label>
                <input id="ur-filter" class="form-control" type="search" placeholder="Matrícula, nome ou curso">
                <select id="ur-status" class="form-control" aria-label="Filtrar situação">
                    <option value="">Todas as situações</option><option value="error">Somente erros</option>
                    <option value="new">Novas contas</option><option value="existing">Contas existentes</option>
                    <option value="duplicate">Linhas repetidas</option>
                </select>
            </div>
            <p class="help-block">O filtro é apenas visual; a confirmação considera todas as abas e linhas válidas.</p>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead><tr><th>Curso / linha</th><th>Matrícula</th><th>Nome completo</th><th>Situação</th><th>Observação</th></tr></thead>
                    <tbody id="ur-rows"></tbody>
                </table>
            </div>
            <div class="ur-pagination"><button id="ur-prev" class="btn btn-default" type="button">Anterior</button>
                <span id="ur-page"></span><button id="ur-next" class="btn btn-default" type="button">Próxima</button></div>
            <hr>
            <div class="checkbox"><label><input id="ur-confirm" type="checkbox">
                Conferi os dados e autorizo criar as novas contas locais. Baixarei as credenciais para entrega segura aos alunos.</label></div>
            <div class="ur-actions">
                <button id="ur-import" class="btn btn-primary" type="button" disabled>Criar contas</button>
                <button id="ur-pause" class="btn btn-default hidden" type="button">Pausar após este lote</button>
                <button id="ur-download" class="btn btn-success" type="button" disabled>Baixar credenciais CSV</button>
                <button id="ur-clear" class="btn btn-default" type="button">Encerrar e apagar dados temporários</button>
            </div>
            <div class="progress ur-progress"><div id="ur-progress" class="progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"></div></div>
            <p id="ur-progress-text" aria-live="polite"></p>
            <p class="help-block">As senhas ficam disponíveis nesta sessão por 30 minutos após o último lote.
                Baixe o CSV antes de encerrar. O arquivo contém dados pessoais e senhas: não publique nem envie a lista completa aos alunos.
                Encerrar não exclui contas já criadas.</p>
        </section>
    </div>
</div>
<script src="/js/userregister.js"></script>