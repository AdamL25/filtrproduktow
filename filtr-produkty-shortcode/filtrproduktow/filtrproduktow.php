<?php
/*
Plugin Name: Filtr Produktów
Description: Wtyczka do filtrowania produktów WooCommerce (kategorie, cena, promocje, sortowanie).
Version: 1.0.1
Author: Promsters
*/

add_shortcode('produkty_filter', function() {
    $search = isset($_GET['szukaj']) ? esc_attr($_GET['szukaj']) : '';
    $sortuj = isset($_GET['sortuj']) ? esc_attr($_GET['sortuj']) : '';
    $kategoria = isset($_GET['kategoria']) ? esc_attr($_GET['kategoria']) : '';
    $min_price = isset($_GET['min_cena']) ? esc_attr($_GET['min_cena']) : '';
    $max_price = isset($_GET['max_cena']) ? esc_attr($_GET['max_cena']) : '';
    $promocja = isset($_GET['promocja']) ? '1' : '';

    ob_start();
    ?>
    <form method="get" action="" id="produkty-filter-form">
        <input type="hidden" name="kategoria" id="filter-hidden-kategoria" value="<?php echo $kategoria; ?>">

        <div class="filter-row">
            <label>Szukaj:</label>
            <input type="text" name="szukaj" value="<?php echo $search; ?>" placeholder="Wpisz frazę...">
        </div>

        <div class="filter-row">
            <label>Sortuj:</label>
            <select name="sortuj" onchange="this.form.submit()">
                <option value="">Domyślnie</option>
                <option value="price_asc" <?php selected($sortuj, 'price_asc'); ?>>Cena rosnąco</option>
                <option value="price_desc" <?php selected($sortuj, 'price_desc'); ?>>Cena malejąco</option>
                <option value="rating_desc" <?php selected($sortuj, 'rating_desc'); ?>>Najlepiej oceniane</option>
                <option value="title_asc" <?php selected($sortuj, 'title_asc'); ?>>Nazwa A-Z</option>
                <option value="title_desc" <?php selected($sortuj, 'title_desc'); ?>>Nazwa Z-A</option>
            </select>
        </div>

        <div class="filter-row">
            <label>Kategorie:</label>
            <ul class="custom-kategorie-lista">
                <li data-slug="" class="<?php echo $kategoria === '' ? 'current' : ''; ?>">Wszystkie kategorie</li>
                <?php
                $categories = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => true, 'parent' => 0]);
                foreach ($categories as $cat) {
                    $is_current = ($cat->slug == $kategoria);
                    echo '<li data-slug="' . esc_attr($cat->slug) . '" class="' . ($is_current ? 'current' : '') . '">' . esc_html($cat->name) . '</li>';
                }
                ?>
            </ul>
        </div>

        <div class="filter-row">
            <label>Cena:</label>
            <div class="price-fields">
                <input type="number" name="min_cena" value="<?php echo $min_price; ?>" placeholder="Cena od">
                <input type="number" name="max_cena" value="<?php echo $max_price; ?>" placeholder="Cena do">
                <button type="submit">Zastosuj</button>
            </div>
        </div>

        <div class="filter-row">
            <label>
                <input type="checkbox" name="promocja" value="1" <?php checked($promocja, '1'); ?> onchange="this.form.submit()">
                Tylko produkty w promocji
            </label>
        </div>
    </form>

    <style>
        .filter-row {
            display: flex;
            flex-direction: column;
        }
        input[type="text"], input[type="number"], select {
            width: 100%;
            padding: 10px;
            font-size: 15px;
            margin-bottom: 10px;
            box-sizing: border-box;
        }
        .custom-kategorie-lista {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .custom-kategorie-lista li {
            margin-bottom: 5px;
            cursor: pointer;
            font-size: 13px;
        }
        .custom-kategorie-lista li.current {
            color: #FF6700;
            font-weight: bold;
        }
        .custom-kategorie-lista li:hover {
            text-decoration: underline;
        }
        .price-fields {
            display: flex;
            flex-direction: column;
            gap: 10px;
            width: 100%;
        }
        .price-fields input[type="number"],
        .price-fields button {
            width: 100% !important;
        }
    </style>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const form = document.getElementById("produkty-filter-form");
            const hiddenField = document.getElementById("filter-hidden-kategoria");
            const items = document.querySelectorAll(".custom-kategorie-lista li");

            items.forEach(item => {
                item.addEventListener("click", () => {
                    const slug = item.getAttribute("data-slug");
                    hiddenField.value = slug;
                    form.submit();
                });
            });
        });
    </script>
    <?php
    return ob_get_clean();
});

add_action('pre_get_posts', function($query) {
    if (!is_admin() && $query->is_main_query() && (is_post_type_archive('product') || is_shop())) {
        $tax_query = [];
        $meta_query = [];

        if (!empty($_GET['kategoria'])) {
            $tax_query[] = [
                'taxonomy' => 'product_cat',
                'field' => 'slug',
                'terms' => sanitize_text_field($_GET['kategoria']),
            ];
        }

        if (!empty($_GET['promocja'])) {
            $meta_query[] = [
                'key' => '_sale_price',
                'value' => 0,
                'compare' => '>',
                'type' => 'NUMERIC',
            ];
        }

        if (!empty($_GET['min_cena']) || !empty($_GET['max_cena'])) {
            $meta_query[] = [
                'key' => '_price',
                'value' => [
                    !empty($_GET['min_cena']) ? floatval($_GET['min_cena']) : 0,
                    !empty($_GET['max_cena']) ? floatval($_GET['max_cena']) : PHP_INT_MAX,
                ],
                'compare' => 'BETWEEN',
                'type' => 'NUMERIC',
            ];
        }

        if (!empty($_GET['szukaj'])) {
            $query->set('s', sanitize_text_field($_GET['szukaj']));
        }

        if (!empty($_GET['sortuj'])) {
            switch ($_GET['sortuj']) {
                case 'price_asc':
                    $query->set('orderby', 'meta_value_num');
                    $query->set('meta_key', '_price');
                    $query->set('order', 'ASC');
                    break;
                case 'price_desc':
                    $query->set('orderby', 'meta_value_num');
                    $query->set('meta_key', '_price');
                    $query->set('order', 'DESC');
                    break;
                case 'rating_desc':
                    $query->set('orderby', 'meta_value_num');
                    $query->set('meta_key', '_wc_average_rating');
                    $query->set('order', 'DESC');
                    break;
                case 'title_asc':
                    $query->set('orderby', 'title');
                    $query->set('order', 'ASC');
                    break;
                case 'title_desc':
                    $query->set('orderby', 'title');
                    $query->set('order', 'DESC');
                    break;
            }
        }

        if (!empty($tax_query)) $query->set('tax_query', $tax_query);
        if (!empty($meta_query)) $query->set('meta_query', $meta_query);
    }
});
