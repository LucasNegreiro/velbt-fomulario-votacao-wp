<?php

function meu_formulario_votacao_menu() {
    add_menu_page('Gerenciar Empresas', 'Gerenciar Empresas', 'manage_options', 'gerenciar-empresas', 'meu_formulario_votacao_admin_page', 'dashicons-building', 6);
    wp_enqueue_media();

}
add_action('admin_menu', 'meu_formulario_votacao_menu');

function meu_formulario_votacao_admin_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'velbt_empresa_votacao';
    $mensagem = '';
    $empresa_id = '';
    $nome = '';
    $logo_url = '';

    // Código para adicionar ou atualizar uma empresa
    if (isset($_POST['submit'])) {
        $nome = sanitize_text_field($_POST['nome']);
        $logo_url = sanitize_text_field($_POST['logo_url']);

        if (isset($_POST['empresa_id']) && $_POST['empresa_id'] != '') {
            // Atualizar
            $empresa_id = intval($_POST['empresa_id']);
            $wpdb->update($table_name, array('nome' => $nome, 'logo_url' => $logo_url), array('id' => $empresa_id));
            $mensagem = 'Empresa atualizada com sucesso!';
        } else {
            // Inserir
            $wpdb->insert($table_name, array('nome' => $nome, 'logo_url' => $logo_url));
            $mensagem = 'Empresa adicionada com sucesso!';
        }

        // Limpar os campos após inserir ou atualizar
        $nome = '';
        $logo_url = '';
        $empresa_id = '';

        // Redirecionar para a mesma página sem o ID da empresa na URL
        //wp_redirect(admin_url('admin.php?page=gerenciar-empresas'));
        //exit;
    }

    // Código para editar uma empresa
    if (isset($_GET['edit'])) {
        $empresa_id = intval($_GET['edit']);
        $empresa = $wpdb->get_row("SELECT * FROM $table_name WHERE id = $empresa_id");
        if ($empresa) {
            $nome = $empresa->nome;
            $logo_url = $empresa->logo_url;
        }
    }

    // Buscar todas as empresas
    $empresas = $wpdb->get_results("SELECT * FROM $table_name");

    echo '<div class="wrap">';
    echo '<h1>Gerenciar Empresas</h1>';

    if ($mensagem != '') {
        echo '<div class="updated"><p>' . $mensagem . '</p></div>';
    }

    if ($empresa_id != '') {
        echo '<h2>Editando Empresa ID: ' . $empresa_id . '</h2>';
    }

    echo '<form method="post" action="">';
    if ($empresa_id != '') {
        echo '<input type="hidden" name="empresa_id" value="' . $empresa_id . '">';
    }
    echo '<table class="form-table">';
    echo '<tr>';
    echo '<th scope="row"><label for="nome">Nome da Empresa</label></th>';
    echo '<td><input type="text" name="nome" id="nome" class="regular-text" value="' . $nome . '" required></td>';
    echo '</tr>';
    echo '<tr>';
    echo '<th scope="row"><label for="logo_url">URL do Logo</label></th>';
    echo '<td><input type="text" name="logo_url" id="logo_url" class="regular-text" value="' . $logo_url . '" required>';
    echo '<input type="button" class="button" value="Anexar da Mídia" onclick="abrirMidia()"></td>';
    echo '</tr>';
    echo '</table>';
    echo '<p class="submit">';
    echo '<input type="submit" name="submit" id="submit" class="button button-primary" value="Adicionar Empresa">';
    echo '<input type="button" name="limpar" id="limpar" class="button button-secondary" value="Limpar" onclick="limparFormulario();">';
    echo '</p>';
    echo '</form>';
    
    //Função Limpar Campos 
    echo '<script type="text/javascript">
        function limparFormulario() {
            document.getElementById("nome").value = "";
            document.getElementById("logo_url").value = "";
        }
    </script>';

    echo '<h2>Lista de Empresas</h2>';
    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead>';
    echo '<tr>';
    echo '<th>ID</th>';
    echo '<th>Nome</th>';
    echo '<th>Logo</th>';
    echo '<th>Ações</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    foreach ($empresas as $empresa) {
        echo '<tr>';
        echo '<td>' . $empresa->id . '</td>';
        echo '<td>' . $empresa->nome . '</td>';
        echo '<td><img src="' . $empresa->logo_url . '" width="50"></td>';
        echo '<td><a href="?page=gerenciar-empresas&edit=' . $empresa->id . '">Editar</a></td>';
        echo '</tr>';
    }
    echo '</tbody>';
    echo '</table>';

    echo '</div>';
    ?>
    <script>
        function abrirMidia() {
            wp.media.editor.send.attachment = function(props, attachment) {
                document.getElementById('logo_url').value = attachment.url;
            }
            wp.media.editor.open();
        }
    </script>
    <?php
}
