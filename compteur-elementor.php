<?php
/**
 * @package compteur_elementor
 * @version 0.0.1
 */
/*
  Plugin Name: Compteur Elementor
  Plugin URI: https://github.com/lafranceinsoumise/compteur-elementor/
  Description: Un shortcode pour compter le nombre de soumissions d'un formulaire elementor.
  Author: Salomé Cheysson
  Version: 0.0.1
  Author URI: https://github.com/aktiur/
*/


function compteur_elementor_shortcode($atts) {
    global $wpdb;

    $atts = shortcode_atts(
        array(
            'formulaire' => NULL,
            'separateur' => '&nbsp;',
            'minutes' => 5
        ), $atts);

    if (!isset($atts['formulaire'])) {
        return "(formulaire=\"[ID]\" manquant)";
    }

    $compteur = wp_cache_get($atts['formulaire'], 'compteur_elementor');

    if (!$compteur) {
        $sql = $wpdb->prepare(
            "SELECT COUNT(*) FROM wp_e_submissions WHERE element_id = %s;",
            $atts['formulaire']
        );

        $compteur = $wpdb->get_var($sql);

        if (!isset($compteur)) {
            // ID incorrecte, probablement
            return "(id de formulaire incorrecte)";
        }

        $compteur = intval($compteur);

        // on cache la valeur
        wp_cache_set($atts['formulaire'], $compteur, 'compteur_elementor', $atts['minutes'] * 60);
    }

    return number_format($compteur, 0, ',', $atts['separateur']);
}

function compteur_elementor_init() {
    add_shortcode('compteur_elementor', 'compteur_elementor_shortcode');
}

add_action('init', 'compteur_elementor_init');

?>
