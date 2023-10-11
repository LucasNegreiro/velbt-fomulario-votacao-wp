<?php
/*
Plugin Name: Velbeet Formulário Votação
Description: Formulário de votação para validar o CPF.
Version: 1.2
Author: Velbeet
*/

include_once 'install.php';
include_once 'admin-menu.php';

function validaCPF($cpf) {
    $cpf = preg_replace('/[^0-9]/is', '', $cpf);
    if (strlen($cpf) != 11 || preg_match('/(\d)\1{10}/', $cpf)) {
        return false;
    }
    for ($t = 9; $t < 11; $t++) {
        for ($d = 0, $c = 0; $c < $t; $c++) {
            $d += $cpf[$c] * (($t + 1) - $c);
        }
        if ($cpf[$c] != ((10 * $d) % 11) % 10) {
            return false;
        }
    }
    return true;
}

register_activation_hook(__FILE__, 'criar_tabelas_votacao');

function velbt_formulario_votacao_scripts() {
    // Add JQuery Mascara
    wp_enqueue_script('jquery-mask', 'https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js', array('jquery'), '1.14.16', true);
    wp_add_inline_script('jquery-mask', '
        jQuery(document).ready(function($) {
            $("#whatsapp").mask("(00) 0 0000-0000");
            $("#cpf").mask("000.000.000-00");
        });
    ');

    // Add o css do arquivo css do plugin
    wp_enqueue_style('meu-formulario-votacao-styles', plugin_dir_url(__FILE__) . 'styles.css');


    // Adiciona CSS do Jquery
    wp_enqueue_style('jquery-ui', 'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css');

    wp_add_inline_script('jquery', $validation_script);
}

add_action('wp_enqueue_scripts', 'velbt_formulario_votacao_scripts');

add_action('wp_ajax_buscar_empresas', 'buscar_empresas_callback');
add_action('wp_ajax_nopriv_buscar_empresas', 'buscar_empresas_callback');

function buscar_empresas_callback() {
    global $wpdb;

    // Verifique se o termo está definido e não está vazio
    if (isset($_REQUEST['term']) && !empty($_REQUEST['term'])) {
        $term = sanitize_text_field($_REQUEST['term']);
    } else {
        error_log('Termo não recebido ou vazio.');
        echo json_encode(['results' => [], 'term' => '']);
        wp_die();
    }

    error_log('Termo recebido: ' . $term);

    $table_name_empresas = $wpdb->prefix . 'velbt_empresa_votacao';
    $empresas = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name_empresas WHERE nome LIKE %s ORDER BY LOCATE(%s, nome), nome ASC", 
        '%' . $wpdb->esc_like($term) . '%',
        $wpdb->esc_like($term)
    ));

    $results = [];
    foreach ($empresas as $empresa) {
        $results[] = [
            'label' => $empresa->nome,
            'value' => $empresa->id,
            'logo'  => $empresa->logo_url
        ];
    }

    echo json_encode(['results' => $results, 'term' => $term]);
    wp_die();
}

// Busca o CPF se já está cadastrado pelo PHP

add_action('wp_ajax_verificar_cpf', 'verificar_cpf_callback');
add_action('wp_ajax_nopriv_verificar_cpf', 'verificar_cpf_callback');

function verificar_cpf_callback() {
    global $wpdb;

    $cpf = sanitize_text_field($_POST['cpf']);

    $table_name = $wpdb->prefix . 'velbt_votacao';
    $cpf_existente = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE cpf = %s", $cpf));

    echo json_encode(['cpf_existente' => $cpf_existente > 0]);
    wp_die();
}




function velbt_formulario_votacao_shortcode() {
    global $wpdb;
    $mensagem = '';
    $mensagemOk = '';
    
    
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $nome = sanitize_text_field($_POST['nome']);
        $cpf = sanitize_text_field($_POST['cpf']);
        $whatsapp = sanitize_text_field($_POST['whatsapp']);
        $empresa_id = intval($_POST['empresa_id']);

            
        // Obtendo o nome da empresa baseado no ID
        $table_name_empresa = $wpdb->prefix . 'velbt_empresa_votacao';
        $empresa = $wpdb->get_row($wpdb->prepare("SELECT nome FROM $table_name_empresa WHERE id = %d", $empresa_id));
        $nome_empresa = $empresa ? $empresa->nome : '';
            
        // Verificar se todos os campos foram preenchidos
        if (empty($nome)) {
            $mensagem = 'Por favor, preencha o campo nome!';
        } elseif (empty($cpf) || !validaCPF($cpf)) {
            $mensagem = 'CPF inválido!';
        } elseif (empty($whatsapp)) {
            $mensagem = 'Por favor, preencha o campo Whatsapp!';
        } elseif (empty($empresa_id)) {
            $mensagem = 'Por favor, selecione uma empresa existente da lista!';
        } else {
            $table_name = $wpdb->prefix . 'velbt_votacao';
            $cpf_existente = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE cpf = %s", $cpf));
            
            if ($cpf_existente > 0) {
                $mensagem = 'CPF já está cadastrado! É permitida apenas uma votação por CPF.';
            } else {
                $wpdb->insert(
                    $table_name,
                    array(
                        'nome' => $nome,
                        'cpf' => $cpf,
                        'whatsapp' => $whatsapp,
                        'empresa_id' => $empresa_id,
                        'nome_empresa' => $nome_empresa  
                    ),
                    array('%s', '%s', '%s', '%d')
                );
                $mensagemOk = 'Votação enviada com sucesso, obrigado por participar!';
                $mensagem = '';
            }
        }
    }

    ob_start();
    ?>
    <div class="meu-formulario">
        <p class="mensagem-ok"><?php echo $mensagemOk; ?></p>
        <form action="" method="post" class="wp-form">
            <div class="form-row">
                <label for="nome">Nome:
                   <i class="info-icon" title="Insira seu nome completo."></i>
                </label>
                <input type="text" name="nome" id="nome" class="regular-text" required>
                <span class="error-message" id="nomeError"></span>
            </div>
            <div class="form-row">
                <label for="cpf">CPF:
                    <i class="info-icon" title="Insira seu número de CPF no formato 999.999.999-99."></i>
                </label>
                <input type="text" name="cpf" id="cpf" pattern="\d{3}\.\d{3}\.\d{3}-\d{2}" class="regular-text" required title="Formato: 999.999.999-99">
                <span class="error-message" id="cpfError"></span>
            </div>
            <div class="form-row">
                <label for="whatsapp">Whatsapp:
                    <i class="info-icon" title="Digite as três primeiras letras do nome da empresa para buscar na lista."></i>
                </label>
                <input type="text" name="whatsapp" id="whatsapp" pattern="\(\d{2}\) \d{1} \d{4}-\d{4}" class="regular-text" required title="Formato: (99) 9 9999-9999">
                <span class="error-message" id="whatsappError"></span>
            </div>
         
            
            <div class="form-row empresa-seletor">
                <label for="empresa">Escolha a empresa:
                    <i class="info-icon" title="Digite as três primeiras letras do nome da empresa para buscar na lista."></i>
                </label>

                <div class="custom-select">
                    <input type="text" id="empresa" placeholder="Digite para buscar uma empresa" />
                    <ul></ul>
                    <div id="loading" style="display: none;">Carregando...</div> <!-- Adicionado indicador de carregamento -->
                    <input type="hidden" name="empresa_id" id="empresa_id">
                </div>

                <span class="error-message" id="empresaError"></span>

            </div>

            <div class="form-row empresa-selecionada" style="display: none;">
                <label>Empresa Selecionada:</label>
                <div id="empresa-info">
                    <!-- As informações da empresa selecionada serão inseridas aqui -->
                </div>
                <button type="button" id="refazer-voto">Refazer Voto</button>
            </div>

            <div class="form-row">
                <input type="submit" value="Votar" class="button button-primary">
            </div>

            <!-- Popup Confirmacao -->
            <div id="reviewModal" class="modal">
                <div class="modal-content">
                    <span class="close">&times;</span>
                    <h2>Revisar Informações</h2>
                    <p>Nome: <span id="reviewNome"></span></p>
                    <p>CPF: <span id="reviewCPF"></span></p>
                    <p>Whatsapp: <span id="reviewWhatsapp"></span></p>
                    <p>Empresa: <span id="reviewEmpresa"></span></p>
                    <button type="button" id="confirmVote">Confirmar Voto</button>
                </div>
            </div>
        </form>
        <p class="mensagem"><?php echo $mensagem; ?></p>
    </div>

    <script>
        jQuery(document).ready(function($) {
            var $customSelect = $('.custom-select');
            var $input = $customSelect.find('input').first();
            var $ul = $customSelect.find('ul');
            var $loading = $('#loading');
            var $empresaSelecionada = $('.empresa-selecionada');
            var $empresaSeletor = $('.empresa-seletor');
            var $empresaInfo = $('#empresa-info');
            var $modal = $('#reviewModal');
            var $span = $('.close');

            $input.on('input', function() {
                var term = $input.val();
                if(term.length >= 3) {
                    $loading.show();
                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        data: {
                            action: 'buscar_empresas',
                            term: term
                        },
                        dataType: 'json',
                        success: function(data) {
                            $ul.empty();
                            if(data.results.length > 0) {
                                data.results.forEach(function(empresa) {
                                    var $li = $('<li data-value="' + empresa.value + '">' +
                                                '<img src="' + empresa.logo + '" alt="' + empresa.label + '" />' +
                                                empresa.label +
                                                '</li>');
                                    $ul.append($li);
                                });
                            } else {
                                $ul.append('<li>Nenhuma empresa encontrada</li>');
                            }
                            $ul.show();
                        },
                        complete: function() {
                            $loading.hide();
                        }
                    });
                }
            });

            function validaCPFScript(cpf) {
                if (cpf.length !== 11 || /^(\d)\1{10}$/.test(cpf)) {
                    return false;
                }

                var add = 0;
                for (var i = 0; i < 9; i++) {
                    add += parseInt(cpf[i]) * (10 - i);
                }

                var rev = 11 - (add % 11);
                if (rev === 10 || rev === 11) {
                    rev = 0;
                }

                if (rev !== parseInt(cpf[9])) {
                    return false;
                }

                add = 0;
                for (var i = 0; i < 10; i++) {
                    add += parseInt(cpf[i]) * (11 - i);
                }

                rev = 11 - (add % 11);
                if (rev === 10 || rev === 11) {
                    rev = 0;
                }

                return rev === parseInt(cpf[10]);
            }

            $ul.on('click', 'li', function() {
                var label = $(this).text().trim();
                var value = $(this).data('value');
                var logo = $(this).find('img').attr('src');
                $input.val(label);
                $('#empresa_id').val(value);
                $ul.hide();

                $empresaInfo.html('<img src="' + logo + '" alt="' + label + '" /><span>' + label + '</span>');
                $empresaSeletor.hide();
                $empresaSelecionada.show();
            });

            $('#refazer-voto').on('click', function() {
                $empresaSelecionada.hide();
                $empresaSeletor.show();
                $input.val('');
                $('#empresa_id').val('');
            });

            $('.wp-form').on('submit', function(e) {  
                e.preventDefault();

                var nome = $('#nome').val().trim();
                var cpf = $('#cpf').val().replace(/[^\d]/g, '');
                var whatsapp = $('#whatsapp').val().trim();
                var empresa_id = $('#empresa_id').val();

                // Limpa mensagens de erro anteriores
                $('.error-message').text('');

                if (!nome) {
                    $('#nomeError').text('Por favor, preencha o campo nome.');
                    return;
                }

                if (!cpf || !validaCPFScript(cpf)) {
                    $('#cpfError').text('CPF inválido! Por favor, insira um CPF válido.');
                    return;
                }

                if (!whatsapp) {
                    $('#whatsappError').text('Por favor, preencha o campo Whatsapp.');
                    return;
                }

                if (!empresa_id) {
                    $('#empresaError').text('Por favor, selecione uma empresa da lista.');
                    return;
                }

                // Verifica se o CPF já foi usado
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    method: 'POST',
                    data: {
                        action: 'verificar_cpf',
                        cpf: cpf
                    },
                    dataType: 'json',
                    success: function(data) {
                        if (data.cpf_existente) {
                            $('#cpfError').text('Este CPF já foi usado para votar.');
                        } else {
                            $('#reviewNome').text(nome);
                            $('#reviewCPF').text(cpf);
                            $('#reviewWhatsapp').text(whatsapp);
                            $('#reviewEmpresa').text($('#empresa').val());

                            $modal.show();
                        }
                    }
                });
            });

            $('#confirmVote').click(function() {
             
                $('.wp-form')[0].submit();

            });

            $span.click(function() {
                $modal.hide();
            });

            $(window).click(function(event) {
                if (event.target == $modal[0]) {
                    $modal.hide();
                }
            });
        });
    </script>


    <?php
    return ob_get_clean();
}


add_shortcode('velbt_formulario_votacao', 'velbt_formulario_votacao_shortcode');
