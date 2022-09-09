<?php
/**
 * @package compteur_elementor
 * @version 0.0.2
 */
/*
  Plugin Name: Compteur Elementor
  Plugin URI: https://github.com/lafranceinsoumise/compteur-elementor/
  Description: Un shortcode pour compter le nombre de soumissions d'un formulaire elementor.
  Author: Salomé Cheysson
  Version: 0.0.2
  Author URI: https://github.com/aktiur/
*/


define('COMPTEUR_ELEMENTOR_CACHE_GROUP', 'compteur_elementor');
define('COMPTEUR_ELEMENTOR_DEFAULT_CACHE_LENGTH', 5);


// the simple request only runs a count on wp_e_submissions and use the element_id_index
// which makes it quite efficient
define('COMPTEUR_ELEMENTOR_SIMPLE_COUNT_QUERY', <<<'SQLEND'
SELECT COUNT(*)
FROM wp_e_submissions
WHERE element_id = %s;
SQLEND);

// the unique request has to join on the wp_e_submissions_values table,
// and uses distinct count; it should be much slower, especially for a
// bigger table.
define('COMPTEUR_ELEMENTOR_UNIQUE_COUNT_QUERY', <<<'SQLEND'
SELECT COUNT(DISTINCT v.value)
FROM wp_e_submissions AS s
JOIN wp_e_submissions_values AS v
ON s.id = v.submission_id
AND v.key = %s
WHERE s.element_id = %s;
SQLEND);


function compteur_elementor_cache_key($formulaire, $unique) {
    if (isset($unique)) {
        return "{$formulaire}:{$unique}";
    } else {
        return $formulaire;
    }
}


function compteur_elementor_calculer_valeur($formulaire, $unique) {
    global $wpdb;

    if (isset($unique)) {
        $sql = $wpdb->prepare(COMPTEUR_ELEMENTOR_UNIQUE_COUNT_QUERY, $unique, $formulaire);
    } else {
        $sql = $wpdb->prepare(COMPTEUR_ELEMENTOR_SIMPLE_COUNT_QUERY, $formulaire);
    }

    return $wpdb->get_var($sql);
}


function compteur_elementor_calculer_cacher_valeur($formulaire, $unique) {
    $compteur = compteur_elementor_calculer_valeur($formulaire, $unique);
    $timestamp = time();

    $cached_value = strval($compteur) . ':' . strval($timestamp);

    wp_cache_set(
        compteur_elementor_cache_key($formulaire, $unique),
        $cached_value,
        COMPTEUR_ELEMENTOR_CACHE_GROUP,
        0  // cacher sans limite pour pouvoir afficher une valeur même périmée
    );

    return $compteur;
}


function compteur_elementor_shortcode($atts) {
    $atts = shortcode_atts(
        array(
            'formulaire' => NULL,
            'separateur' => '&nbsp;',
            'minutes' => NULL,
            'unique' => NULL
        ), $atts);

    // On interdit une durée en minutes égale à zéro parce que cela bloquerait le compteur
    // à la première valeur calculée, jusqu'à ce que le cache soit manuellement flushé.
    $atts['minutes'] = floatval($atts['minutes']);
    if ($atts['minutes'] === 0.) {
        $atts['minutes'] = COMPTEUR_ELEMENTOR_DEFAULT_CACHE_LENGTH;
    }

    $expiration = intval($atts['minutes'] * 60);

    if (!isset($atts['formulaire'])) {
        return "0<!-- id du formulaire manquant -->";
    }

    $cache_key = compteur_elementor_cache_key($atts['formulaire'], $atts['unique']);

    // la valeur stockée est de la forme '[compteur]:[timestamp]'
    $cached_value = wp_cache_get($cache_key, COMPTEUR_ELEMENTOR_CACHE_GROUP);
    $valeur_compteur = 0;
    $timestamp = 0;
    $now = time();
    $status = 'error';

    if ($cached_value) {
        $elements = explode(':', $cached_value);
        $valeur_compteur = intval($elements[0]);
        if (count($elements) > 1) {
            $timestamp = intval($elements[1]);
        }
        $status = 'cached';
    }

    if (!$valeur_compteur || $now > $timestamp + $expiration) {
        $status = 'stale';
        $args = [$atts['formulaire'], $atts['unique']];

        // on programme du cron sauf si c'est déjà programmé
        if (!wp_next_scheduled(
            'compteur_elementor_calculer_cacher_valeur',
            $args
        )) {
            wp_schedule_single_event(
                $now,
                'compteur_elementor_calculer_valeur_action',
                $args,
                true
            );
        }
    }

    $compteur_format = number_format($valeur_compteur, 0, ',', $atts['separateur']);

    return "{$compteur_format}<!-- {$status} -->";
}


function compteur_elementor_init() {
    add_action(
        'compteur_elementor_calculer_valeur_action',
        'compteur_elementor_calculer_cacher_valeur',
        10,
        2
    );
    add_shortcode('compteur_elementor', 'compteur_elementor_shortcode');
}

add_action('init', 'compteur_elementor_init');

if ( defined( 'WP_CLI' ) && WP_CLI ) {
    require_once 'cli.php';
}

?>
