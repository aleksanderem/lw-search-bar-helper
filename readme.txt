=== LW Search Bar Helper ===
Contributors: liskadev
Requires at least: 5.9
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.3

Eksponuje dane mieszkań (CPT lokal) przez REST API /wp-json/lw/v1/apartments.

== Description ==

Lekki plugin instalowany na zdalnych stronach WordPress (np. rezydencjaliwska2.pl, rezydencjaliwska3.pl),
który udostępnia publiczny endpoint REST API z danymi mieszkań.

Główny plugin LW Search Bar na stronie agregującej odpytuje ten endpoint, aby połączyć dane z wielu inwestycji.

== Changelog ==

= 1.0.3 =
* Na pojedynczym lokalu widget Elementora z klasą `kolabo-ceny-table` nie jest renderowany (brak HTML), gdy status jest na liście `lw_helper_hide_price_statuses`. Lista klas: filtr `lw_helper_hide_price_css_classes` (domyślnie `kolabo-ceny-table`). Edytor, podgląd Elementora i wp-admin bez zmian. Rozpoznawanie statusu jest wspólne z REST API.

= 1.0.2 =
* Dla statusu `sprzedane` publiczny endpoint zwraca `null` w polach `price_total`, `price_m2`, `price_total_formatted` i `price_m2_formatted` oraz pustą tablicę `price_history`. Lista statusów ukrywających ceny jest filtrowalna: `lw_helper_hide_price_statuses` (domyślnie `sprzedane`). Kształt odpowiedzi (zestaw kluczy) bez zmian.

= 1.0.0 =
* Pierwsza wersja — wydzielenie REST endpoint z lw-search-bar
