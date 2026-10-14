/* global jQuery */
(function ($) {
    'use strict';
    $(function () {
        var base = '/api/user_register/settings/';
        var job = null, busy = false, paused = false, page = 0, pageSize = 50;
        var labels = {new: 'Nova conta', existing: 'Preservada', duplicate: 'Repetida', error: 'Corrigir'};
        var colors = {new: 'success', existing: 'default', duplicate: 'warning', error: 'danger'};

        function message(text, type) {
            $('#ur-message').removeClass('hidden alert-danger alert-success alert-info alert-warning')
                .addClass('alert-' + (type || 'info')).text(text);
        }
        function failure(xhr) {
            message((xhr.responseJSON && xhr.responseJSON.message) ||
                'A requisição falhou. Confira a sessão e a conexão; recarregue a página antes de retomar.', 'danger');
        }
        function request(action, data) {
            // OPNsense's default layout attaches the session CSRF header to same-origin AJAX.
            return $.ajax({url: base + action, type: 'POST', dataType: 'json',
                contentType: 'application/json', data: JSON.stringify(data), timeout: 120000});
        }
        function setBusy(value) {
            busy = value;
            $('#ur-preview, #ur-file, #ur-expiry, #ur-clear, #ur-confirm').prop('disabled', value);
            $('#ur-pause').toggleClass('hidden', !value);
            buttons();
        }
        function buttons() {
            $('#ur-import').prop('disabled', busy || !job || job.summary.error > 0 ||
                (job.done && job.sync_pending === 0) || !$('#ur-confirm').prop('checked'))
                .text(job && job.done ? 'Sincronizar contas pendentes' : (job && job.offset ? 'Retomar criação' : 'Criar contas'));
            $('#ur-download').prop('disabled', busy || !job || !job.created);
        }
        function renderTable() {
            if (!job) { return; }
            var term = $('#ur-filter').val().toLocaleLowerCase(), status = $('#ur-status').val();
            var rows = job.records.filter(function (row) {
                return (!status || row.status === status) &&
                    (row.username + ' ' + row.name + ' ' + row.course).toLocaleLowerCase().indexOf(term) !== -1;
            });
            var pages = Math.max(1, Math.ceil(rows.length / pageSize));
            page = Math.min(page, pages - 1);
            var body = $('#ur-rows').empty();
            rows.slice(page * pageSize, (page + 1) * pageSize).forEach(function (row) {
                var tr = $('<tr>');
                $('<td>').text(row.course + ' / ' + row.line).appendTo(tr);
                $('<td>').text(row.username).appendTo(tr);
                $('<td>').text(row.name).appendTo(tr);
                $('<td>').append($('<span>').addClass('label label-' + colors[row.status]).text(labels[row.status])).appendTo(tr);
                $('<td>').text(row.error).appendTo(tr);
                body.append(tr);
            });
            $('#ur-page').text('Página ' + (page + 1) + ' de ' + pages + ' · ' + rows.length + ' linhas');
            $('#ur-prev').prop('disabled', page === 0);
            $('#ur-next').prop('disabled', page >= pages - 1);
        }
        function render(next) {
            if (job && !next.records) { next.records = job.records; }
            job = next;
            $('#ur-review').removeClass('hidden');
            $('#ur-total').text(job.summary.total);
            $('#ur-new').text(job.summary.new);
            $('#ur-existing').text(job.summary.existing + job.summary.duplicate);
            $('#ur-errors').text(job.summary.error);
            $('#ur-details').text(job.sheets.length + ' abas: ' + job.sheets.join(', ') +
                ' · Validade: ' + (job.expires || 'sem expiração'));
            var progress = job.summary.new ? Math.round(job.offset / job.summary.new * 100) : 100;
            $('#ur-progress').css('width', progress + '%').attr('aria-valuenow', progress);
            $('#ur-progress-text').text(job.created + ' contas criadas · ' + job.offset + '/' + job.summary.new +
                ' processadas · ' + job.skipped + ' preservadas por alteração concorrente · ' + job.sync_pending + ' sincronizações pendentes');
            renderTable();
            buttons();
        }
        function advance() {
            if (paused) { setBusy(false); message('Pausado. Os lotes concluídos foram salvos; você pode baixar as credenciais e retomar.'); return; }
            if (job.done && !job.sync_pending) {
                setBusy(false); message('Importação concluída. Baixe as credenciais e entregue cada acesso individualmente.', 'success'); return;
            }
            var action = job.done ? 'sync' : 'import';
            request(action, {token: job.token, offset: job.offset, confirm: true}).done(function (data) {
                render(data.job);
                if (data.status === 'warning') { setBusy(false); message(data.message, 'warning'); return; }
                advance();
            }).fail(function (xhr) { setBusy(false); failure(xhr); });
        }
        $('#ur-upload').on('submit', function (event) {
            event.preventDefault();
            var file = $('#ur-file')[0].files[0];
            if (!file || !/\.xlsx$/i.test(file.name) || file.size > 5242880) {
                message('Selecione uma planilha .xlsx de até 5 MiB.', 'danger'); return;
            }
            if (job && job.created) { message('Baixe as credenciais e encerre a importação anterior antes de enviar outra.', 'warning'); return; }
            var form = new FormData(); form.append('file', file); form.append('expires', $('#ur-expiry').val());
            setBusy(true); message('Analisando todas as abas. Nenhuma conta está sendo criada.');
            $.ajax({url: base + 'preview', type: 'POST', data: form, processData: false,
                contentType: false, dataType: 'json', timeout: 120000}).done(function (data) {
                page = 0; $('#ur-confirm').prop('checked', false); render(data.job);
                message(job.summary.error ? 'Há erros na planilha. Corrija as linhas indicadas e envie o arquivo novamente.' :
                    'Prévia pronta. Confira os dados antes de autorizar o cadastro.', job.summary.error ? 'warning' : 'success');
            }).fail(failure).always(function () { setBusy(false); });
        });
        $('#ur-confirm').on('change', buttons);
        $('#ur-import').on('click', function () { paused = false; setBusy(true); message('Processando lotes. É possível pausar sem desfazer contas já criadas.'); advance(); });
        $('#ur-pause').on('click', function () { paused = true; message('A pausa será aplicada ao concluir a requisição em andamento.'); });
        $('#ur-filter, #ur-status').on('input change', function () { page = 0; renderTable(); });
        $('#ur-prev').on('click', function () { page--; renderTable(); });
        $('#ur-next').on('click', function () { page++; renderTable(); });
        $('#ur-download').on('click', function () {
            setBusy(true);
            request('download', {token: job.token}).done(function (data) {
                var url = URL.createObjectURL(new Blob([data.csv], {type: 'text/csv;charset=utf-8'}));
                var link = document.createElement('a'); link.href = url; link.download = 'credenciais-alunos.csv';
                document.body.appendChild(link); link.click(); link.remove();
                window.setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
                message('Download solicitado. Confirme que o CSV foi salvo antes de apagar os dados temporários.', 'success');
            }).fail(failure).always(function () { setBusy(false); });
        });
        $('#ur-clear').on('click', function () {
            if (!window.confirm('Apagar a prévia e as senhas temporárias? Contas já criadas permanecem no OPNsense. Confirme que salvou o CSV.')) { return; }
            setBusy(true);
            request('clear', {token: job.token}).done(function () {
                job = null; $('#ur-review').addClass('hidden'); $('#ur-upload')[0].reset();
                message('Dados temporários removidos da sessão. Contas existentes foram preservadas.', 'success');
            }).fail(failure).always(function () { setBusy(false); });
        });
        window.addEventListener('beforeunload', function (event) {
            if (busy || (job && job.created)) { event.preventDefault(); event.returnValue = ''; }
        });
        $.ajax({url: base + 'get', type: 'GET', dataType: 'json', cache: false}).done(function (data) {
            if (data.job) { render(data.job); message('Importação anterior recuperada nesta sessão. Confira o progresso antes de retomar.'); }
        }).fail(failure);
    });
}(jQuery));
