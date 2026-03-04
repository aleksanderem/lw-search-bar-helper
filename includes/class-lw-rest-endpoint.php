<?php
if (!defined('ABSPATH')) exit;

class LW_Rest_Endpoint {

    public function register_routes() {
        register_rest_route('lw/v1', '/apartments', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_apartments'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function get_apartments() {
        $q = new WP_Query([
            'post_type'      => 'lokal',
            'posts_per_page' => -1,
            'no_found_rows'  => true,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);

        $investment = get_option('lw_investment_name', '');
        $apartments = [];

        while ($q->have_posts()) {
            $q->the_post();
            $post_id = get_the_ID();
            $title   = get_the_title();

            // Status
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

            // Rooms
            $rooms_terms = get_the_terms($post_id, 'liczba_pokoi');
            $rooms = 0;
            if (is_array($rooms_terms) && !empty($rooms_terms)) {
                $rooms = (int) preg_replace('/\D/', '', $rooms_terms[0]->name);
            }

            // Floor
            $floor_terms = get_the_terms($post_id, 'pietro');
            $floor = '—';
            $floor_num = 0;
            if (is_array($floor_terms) && !empty($floor_terms)) {
                $floor = $floor_terms[0]->name;
                if (mb_strtolower($floor) === 'parter') {
                    $floor_num = 0;
                } else {
                    $floor_num = (int) preg_replace('/\D/', '', $floor);
                }
            }

            // Prices
            $prices = $this->get_prices($post_id);

            // Thumbnail — catalog card preview image, fallback to featured image
            $thumbnail = '';
            if (function_exists('get_field')) {
                $card_img = get_field('karta_katalogowa_podglad_obrazek', $post_id);
                if (is_array($card_img) && !empty($card_img['url'])) {
                    $thumbnail = $card_img['sizes']['medium'] ?? $card_img['url'];
                } elseif (is_string($card_img) && $card_img) {
                    $thumbnail = $card_img;
                }
            }
            if (!$thumbnail && has_post_thumbnail($post_id)) {
                $thumbnail = get_the_post_thumbnail_url($post_id, 'medium');
            }

            // 3D gallery (rzuty_3d)
            $gallery_3d = [];
            if (function_exists('get_field')) {
                $rzuty = get_field('rzuty_3d', $post_id);
                if (is_array($rzuty)) {
                    foreach ($rzuty as $img) {
                        if (is_array($img) && !empty($img['url'])) {
                            $gallery_3d[] = $img['url'];
                        }
                    }
                }
            }

            // PDF catalog card
            $pdf_url = '';
            if (function_exists('get_field')) {
                $pdf = get_field('karta_katalogowa_pdf', $post_id);
                if (is_array($pdf) && !empty($pdf['url'])) {
                    $pdf_url = $pdf['url'];
                } elseif (is_string($pdf) && $pdf) {
                    $pdf_url = $pdf;
                }
            }

            // Inquiry URL
            $inquiry_url = home_url('/wyslij-zapytanie/');
            $inquiry_url = add_query_arg([
                'title'      => 'Mieszkanie ' . $title,
                'nr_lokalu'  => $title,
            ], $inquiry_url);

            // Price history
            $price_history = [];
            $history_raw = get_post_meta($post_id, 'kolabo_price_history', true);
            $last_mod = get_the_modified_time('Y-m-d H:i');
            if (is_array($history_raw) && !empty($history_raw)) {
                foreach ($history_raw as $row) {
                    $when = !empty($row['ts']) ? get_date_from_gmt($row['ts'], 'Y-m-d H:i') : $last_mod;
                    $human_total = !empty($row['human_total']) ? $row['human_total'] : ((isset($row['total']) && $row['total'] !== null) ? number_format((float)$row['total'], 0, ',', ' ') . ' zł' : '—');
                    $price_history[] = [
                        'price' => wp_strip_all_tags(html_entity_decode($human_total)),
                        'date'  => $when,
                    ];
                }
            }

            $apartments[] = [
                'title'                 => $title,
                'link'                  => get_permalink(),
                'status'                => $status_slug,
                'status_name'           => $status_name,
                'rooms'                 => $rooms,
                'floor'                 => $floor,
                'floor_num'             => $floor_num,
                'area'                  => $prices['area'],
                'price_total'           => $prices['total'],
                'price_m2'              => $prices['m2'],
                'price_total_formatted' => html_entity_decode($prices['human_total'], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'price_m2_formatted'    => html_entity_decode($prices['human_m2'], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'investment'            => $investment,
                'thumbnail'             => $thumbnail ?: '',
                'gallery_3d'            => $gallery_3d,
                'pdf_url'               => $pdf_url,
                'inquiry_url'           => esc_url_raw($inquiry_url),
                'price_history'         => $price_history,
            ];
        }
        wp_reset_postdata();

        return rest_ensure_response($apartments);
    }

    private function get_prices($post_id) {
        if (function_exists('kolabo_current_price_snapshot')) {
            return kolabo_current_price_snapshot($post_id);
        }

        // Fallback — same logic as kolabo_current_price_snapshot
        $num = function ($val) {
            if ($val === '' || $val === null) return null;
            $val = html_entity_decode((string) $val, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $val = str_replace(["\xC2\xA0", '&nbsp;', ' '], '', $val);
            $val = str_replace(',', '.', $val);
            return is_numeric($val) ? (float) $val : null;
        };

        $raw_total = function_exists('get_field') ? get_field('cena_mieszkania', $post_id) : get_post_meta($post_id, 'cena_mieszkania', true);
        $raw_m2    = function_exists('get_field') ? get_field('cena_za_metr', $post_id) : get_post_meta($post_id, 'cena_za_metr', true);
        $raw_area  = function_exists('get_field') ? get_field('metraz', $post_id) : get_post_meta($post_id, 'metraz', true);

        $total = $num($raw_total);
        $m2    = $num($raw_m2);
        $area  = $num($raw_area);

        if ($total === null && $m2 !== null && $area !== null) {
            $total = $m2 * $area;
        }
        if ($m2 === null && $total !== null && $area !== null && $area > 0) {
            $m2 = $total / $area;
        }

        return [
            'total'       => $total,
            'm2'          => $m2,
            'area'        => $area,
            'human_total' => ($total !== null) ? number_format($total, 0, ',', ' ') . ' zł' : '',
            'human_m2'    => ($m2 !== null) ? number_format($m2, 2, ',', ' ') . ' zł/m²' : '',
        ];
    }
}
