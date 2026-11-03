<?php
if (!defined('ABSPATH')) exit;

/**
 * Shared status resolution for public price hiding.
 *
 * REST (/wp-json/lw/v1/apartments) and the Elementor single-lokal template
 * both use this class, so lw_helper_hide_price_statuses stays one list.
 */
class LW_Helper_Price_Visibility {

    /**
     * Status slug and label for a lokal, using the same priority as the REST payload.
     *
     * @param int $post_id
     * @return array slug and name keys.
     */
    public static function resolve_status($post_id) {
        $status_slug = '';
        $status_name = '—';
        if (has_term('dostepne', 'status_mieszkania', $post_id)) {
            $status_slug = 'dostepne';
            $status_name = 'Dostępne';
        } elseif (has_term('zarezerwowane', 'status_mieszkania', $post_id)) {
            $status_slug = 'zarezerwowane';
            $status_name = 'Rezerwacja';
        } elseif (has_term('sprzedane', 'status_mieszkania', $post_id)) {
            $status_slug = 'sprzedane';
            $status_name = 'Sprzedane';
        }

        return array(
            'slug' => $status_slug,
            'name' => $status_name,
        );
    }

    /**
     * Status slugs whose public prices are omitted.
     *
     * @return string[]
     */
    public static function hidden_statuses() {
        /**
         * Status slugs whose prices are omitted from the public apartments
         * endpoint and from matching Elementor widgets on a single lokal.
         *
         * @param string[] $statuses Default hides sprzedane only.
         */
        $statuses = apply_filters('lw_helper_hide_price_statuses', array('sprzedane'));
        if (!is_array($statuses)) {
            $statuses = array('sprzedane');
        }

        return $statuses;
    }

    /**
     * @param string $status_slug Slug from taxonomy status_mieszkania.
     * @return bool
     */
    public static function should_hide_prices($status_slug) {
        return in_array((string) $status_slug, self::hidden_statuses(), true);
    }

    /**
     * @param int $post_id
     * @return bool
     */
    public static function should_hide_post($post_id) {
        $status = self::resolve_status($post_id);
        return self::should_hide_prices($status['slug']);
    }
}
