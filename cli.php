<?php

function compteur_elementor_cli( $args, $assoc_args ) {
    if (count ( $args ) < 1 || count( $args ) > 2) {
        WP_CLI::line("Usage: wp compteur_elementor formulaire [unique]");
        WP_CLI::halt(1);
    }

    $formulaire = $args[0];
    $unique = NULL;
    if (count($args) > 1) {
        $unique = $args[1];
    }

    $compteur_value = compteur_elementor_calculer_cacher_valeur($formulaire, $unique);
    WP_CLI::line($compteur_value);
}

WP_CLI::add_command(
    'compteur-elementor',
    'compteur_elementor_cli',
    array(
        "shortdesc" => "Recalcule et cache la valeur d'un compteur elementor",
    )
);

?>
