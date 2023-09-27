<?php

function criar_tabelas_votacao() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    // Tabela de empresas
    $table_name_empresas = $wpdb->prefix . 'velbt_empresa_votacao';

    $sql_empresas = "CREATE TABLE $table_name_empresas (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        nome varchar(255) NOT NULL,
        logo_url varchar(255) NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_empresas);

    // Tabela de votação
    $table_name = $wpdb->prefix . 'velbt_votacao';

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        nome varchar(255) NOT NULL,
        nome_empresa varchar(255) NOT NULL,
        cpf varchar(14) NOT NULL,
        whatsapp varchar(16) NOT NULL,
        empresa_id mediumint(9) NOT NULL,
        time datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    dbDelta($sql);

    // Adicionando chave estrangeira manualmente
    if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
        $wpdb->query("ALTER TABLE $table_name ADD FOREIGN KEY (empresa_id) REFERENCES $table_name_empresas(id)");
    }
}
