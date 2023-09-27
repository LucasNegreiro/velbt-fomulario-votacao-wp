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

    // Adiciona o script e o CSS do Select2
    wp_enqueue_script('select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js', array('jquery'), '4.0.13', true);
    wp_enqueue_style('select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css');
    
    // Adiciona CSS do Jquery
    wp_enqueue_style('jquery-ui', 'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css');


    $validation_script = <<<EOD
        jQuery(document).ready(function($) {
            $('.wp-form').on('submit', function(e) {
                var empresaId = $('#empresa_id').val();
                if (!empresaId) {
                    e.preventDefault();
                    alert('Por favor, selecione uma empresa existente da lista!');
                }
            });
        });
    EOD;

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





function velbt_formulario_votacao_shortcode() {
    global $wpdb;
    $mensagem = '';
    
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $nome = sanitize_text_field($_POST['nome']);
        $cpf = sanitize_text_field($_POST['cpf']);
        $whatsapp = sanitize_text_field($_POST['whatsapp']);
        $empresa_id = intval($_POST['empresa_id']);
        
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
                        'empresa_id' => $empresa_id
                    ),
                    array('%s', '%s', '%s', '%d')
                );
                $mensagem = 'Votação enviada com sucesso, obrigado por participar!';
            }
        }
    }

    ob_start();
    ?>
    <div class="meu-formulario">
        <form action="" method="post" class="wp-form">
            <div class="form-row">
                <label for="nome">Nome:</label>
                <input type="text" name="nome" id="nome" class="regular-text" required>
            </div>
            <div class="form-row">
                <label for="cpf">CPF:</label>
                <input type="text" name="cpf" id="cpf" pattern="\d{3}\.\d{3}\.\d{3}-\d{2}" class="regular-text" required title="Formato: 999.999.999-99">
            </div>
            <div class="form-row">
                <label for="whatsapp">Whatsapp:</label>
                <input type="text" name="whatsapp" id="whatsapp" pattern="\(\d{2}\) \d{1} \d{4}-\d{4}" class="regular-text" required title="Formato: (99) 9 9999-9999">
            </div>
            <div class="form-row">            
                <label for="empresa_nome">Empresa:</label>
                <select id="empresa_nome" name="empresa_nome" style="width: 100%;"></select>
                <input type="hidden" name="empresa_id" id="empresa_id">
            </div>
            <div class="form-row">            
                <div id="selectedEmpresa" class="selected-empresa"></div> <!-- Adicionado um novo elemento para mostrar a empresa selecionada -->
            </div>
            <div class="form-row">
                <input type="submit" value="Enviar" class="button button-primary">
            </div>
        </form>
        <p class="mensagem"><?php echo $mensagem; ?></p>
    </div>
    <script>
        jQuery(document).ready(function($) {
            console.log('Documento pronto');

            var $empresaNome = $('#empresa_nome');

            console.log('Elemento #empresa_nome encontrado:', $empresaNome.length > 0);

            if ($.fn.select2) {
                console.log('Select2 está disponível');

                $empresaNome.select2({
                    language: {
                        errorLoading: function () {
                            return 'Os resultados não puderam ser carregados.';
                        },
                        inputTooLong: function (args) {
                            var overChars = args.input.length - args.maximum;
                            return 'Por favor, apague ' + overChars + ' letras';
                        },
                        inputTooShort: function (args) {
                            var remainingChars = args.minimum - args.input.length;
                            return 'Por favor, insira ' + remainingChars + ' ou mais letras';
                        },
                        loadingMore: function () {
                            return 'Carregando mais resultados…';
                        },
                        maximumSelected: function (args) {
                            return 'Você só pode selecionar ' + args.maximum + ' empresa';
                        },
                        noResults: function () {
                            return 'Nenhum resultado encontrado';
                        },
                        searching: function () {
                            return 'Buscando…';
                        }
                    },
                    placeholder: 'Selecione uma empresa',
                    ajax: {
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        dataType: 'json',
                        delay: 250,
                        data: function (params) {
                            return {
                                action: 'buscar_empresas',
                                term: params.term || '',
                                page: params.page || 1
                            };
                        },
                        processResults: function (data) {
                            return {
                                results: data.results,
                                pagination: {
                                    more: data.results.length === 10
                                }
                            };
                        }
                    },
                    escapeMarkup: function (markup) { return markup; },
                    minimumInputLength: 1,
                    templateResult: formatRepo,
                    templateSelection: formatRepoSelection
                });

                if ($empresaNome.data('select2')) {
                    console.log('Select2 foi inicializado');

                    $empresaNome[0].addEventListener('change', function(e) {
                        console.log('Evento change disparado no elemento DOM');
                        console.log('Objeto do evento:', e);
                    });

                    $empresaNome.on('select2:select', function (e) {
                        console.log('Evento select2:select disparado');
                        var data = e.params.data;
                        console.log('Dados selecionados:', data);
                    });
                } else {
                    console.log('Select2 não foi inicializado');
                }
            } else {
                console.log('Select2 não está disponível');
            }

            


            function formatRepo(repo) {
                if (repo.loading) return repo.text;
                var markup = "<div class='select2-result-repository clearfix'>" +
                    "<img src='" + repo.logo + "' width='30' height='30' />" +
                    "<div class='select2-result-repository__title'>" + repo.label + "</div></div>";
                return markup;
            }

            function formatRepoSelection(repo) {
                return repo.label || repo.text;
            }
        });
    </script>




    <?php
    return ob_get_clean();
}

add_shortcode('velbt_formulario_votacao', 'velbt_formulario_votacao_shortcode');
