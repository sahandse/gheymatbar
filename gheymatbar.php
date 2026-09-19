<?php
/**
 * Plugin Name: قیمت‌بار
 * Plugin URI: https://github.com/sahandse/gheymatbar
 * Description: نمایش قیمت طلا، سکه، ارز و رمزارز با شورت‌کدهای مجزا، تم‌های قابل تنظیم و رابط فارسی.
 * Version: 1.1.0
 * Author: Sahand Rezvan
 * Author URI: https://github.com/sahandse
 * Text Domain: gheymatbar
 * Requires at least: 6.2
 * Requires PHP: 7.4
 */

defined('ABSPATH') || exit;

final class Gheymatbar_Plugin {
    const VERSION = '1.1.0';
    const OPTION  = 'gheymatbar_settings';

    public function __construct() {
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
        add_shortcode('gheymatbar_gold', [$this, 'gold_shortcode']);
        add_shortcode('gheymatbar_coin', [$this, 'coin_shortcode']);
        add_shortcode('gheymatbar_currency', [$this, 'currency_shortcode']);
        add_shortcode('gheymatbar_crypto', [$this, 'crypto_shortcode']);
        add_shortcode('gheymatbar_all', [$this, 'all_shortcode']);
    }

    public function defaults() {
        return [
            'layout' => 'horizontal',
            'theme' => 'light',
            'accent' => '#111827',
            'refresh_minutes' => 10,
            'source_note' => 'داده‌ها از منابع عمومی/رایگان دریافت می‌شوند.',
        ];
    }

    public function settings() {
        return wp_parse_args((array)get_option(self::OPTION, []), $this->defaults());
    }

    public function register_settings() {
        register_setting('gheymatbar_group', self::OPTION, [$this, 'sanitize_settings']);
    }

    public function sanitize_settings($in) {
        $d = $this->defaults();
        return [
            'layout' => in_array($in['layout'] ?? '', ['horizontal','vertical'], true) ? $in['layout'] : $d['layout'],
            'theme' => in_array($in['theme'] ?? '', ['light','dark','glass'], true) ? $in['theme'] : $d['theme'],
            'accent' => sanitize_hex_color($in['accent'] ?? '') ?: $d['accent'],
            'refresh_minutes' => min(60, max(1, absint($in['refresh_minutes'] ?? 10))),
            'source_note' => sanitize_text_field($in['source_note'] ?? $d['source_note']),
        ];
    }

    public function admin_menu() {
        if (function_exists('s_store_register_submenu')) {
            s_store_register_submenu('gheymatbar', 'قیمت‌بار', [$this, 'settings_page'], 'manage_options', 'قیمت‌بار');
            return;
        }
        add_menu_page(
            'قیمت‌بار',
            'قیمت‌بار',
            'manage_options',
            'gheymatbar',
            [$this, 'settings_page'],
            'dashicons-chart-line',
            58
        );
    }

    public function admin_assets($hook) {
        if (false === strpos($hook, 'gheymatbar')) return;
        wp_enqueue_style('gheymatbar-admin', plugin_dir_url(__FILE__) . 'assets/admin.css', [], self::VERSION);
    }

    public function settings_page() {
        if (!current_user_can('manage_options')) return;
        $s = $this->settings();
        ?>
        <div class="wrap gheymatbar-admin">
            <div class="gheymatbar-hero">
                <div>
                    <h1>قیمت‌بار</h1>
                    <p>نمایش قیمت طلا، سکه، ارز و رمزارز با شورت‌کدهای جداگانه.</p>
                </div>
                <span>v<?php echo esc_html(self::VERSION); ?></span>
            </div>

            <form method="post" action="options.php">
                <?php settings_fields('gheymatbar_group'); ?>
                <div class="gheymatbar-grid">
                    <section class="gheymatbar-card">
                        <h2>نمایش</h2>
                        <label>چیدمان
                            <select name="<?php echo self::OPTION; ?>[layout]">
                                <option value="horizontal" <?php selected($s['layout'],'horizontal'); ?>>افقی</option>
                                <option value="vertical" <?php selected($s['layout'],'vertical'); ?>>عمودی</option>
                            </select>
                        </label>
                        <label>تم
                            <select name="<?php echo self::OPTION; ?>[theme]">
                                <option value="light" <?php selected($s['theme'],'light'); ?>>روشن</option>
                                <option value="dark" <?php selected($s['theme'],'dark'); ?>>تیره</option>
                                <option value="glass" <?php selected($s['theme'],'glass'); ?>>شیشه‌ای</option>
                            </select>
                        </label>
                        <label>رنگ اصلی
                            <input type="color" name="<?php echo self::OPTION; ?>[accent]" value="<?php echo esc_attr($s['accent']); ?>">
                        </label>
                    </section>

                    <section class="gheymatbar-card">
                        <h2>داده</h2>
                        <label>بازه بروزرسانی (دقیقه)
                            <input type="number" min="1" max="60" name="<?php echo self::OPTION; ?>[refresh_minutes]" value="<?php echo esc_attr($s['refresh_minutes']); ?>">
                        </label>
                        <label>متن منبع
                            <input type="text" name="<?php echo self::OPTION; ?>[source_note]" value="<?php echo esc_attr($s['source_note']); ?>">
                        </label>
                    </section>

                    <section class="gheymatbar-card">
                        <h2>شورت‌کدها</h2>
                        <code>[gheymatbar_gold]</code>
                        <code>[gheymatbar_coin]</code>
                        <code>[gheymatbar_currency]</code>
                        <code>[gheymatbar_crypto]</code>
                        <code>[gheymatbar_all]</code>
                    </section>

                    <section class="gheymatbar-card">
                        <h2>وضعیت اتصال داده</h2>
                        <p>داده‌های طلا، سکه و ارز از صفحات عمومی TGJU با Cache داخلی دریافت می‌شوند؛ رمزارزها از API عمومی CoinGecko خوانده می‌شوند. در صورت قطع منبع، آخرین Cache معتبر نمایش داده می‌شود.</p>
                    </section>
                </div>
                <?php submit_button('ذخیره تنظیمات'); ?>
            </form>
        </div>
        <?php
    }

    private function fetch_tgju($key) {
        $s=$this->settings();
        $cache_key='gheymatbar_tgju_'.sanitize_key($key);
        $cached=get_transient($cache_key);
        if(is_array($cached)&&isset($cached['value'])) return $cached;

        $url='https://www.tgju.org/profile/'.rawurlencode($key).'/today';
        $res=wp_remote_get($url,['timeout'=>15,'redirection'=>3,'headers'=>['User-Agent'=>'Mozilla/5.0 Gheymatbar/'.self::VERSION,'Accept-Language'=>'fa-IR,fa;q=0.9']]);
        if(is_wp_error($res)||200!==(int)wp_remote_retrieve_response_code($res)){
            $fallback=get_option($cache_key.'_last');
            return is_array($fallback)?$fallback:new WP_Error('gheymatbar_source','دریافت داده از TGJU ناموفق بود.');
        }

        $text=wp_strip_all_tags(wp_remote_retrieve_body($res));
        $text=preg_replace('/\s+/u',' ',$text);
        if(!preg_match('/نرخ فعلی\s*:?\s*:?\s*([0-9۰-۹٠-٩,]+)/u',$text,$m)){
            $fallback=get_option($cache_key.'_last');
            return is_array($fallback)?$fallback:new WP_Error('gheymatbar_parse','قیمت در منبع پیدا نشد.');
        }

        $digits=strtr($m[1],['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9']);
        $value=(float)str_replace(',','',$digits);
        if($value<=0) return new WP_Error('gheymatbar_value','قیمت معتبر نیست.');

        $data=['value'=>$value,'unit'=>'ریال','time'=>current_time('timestamp'),'source'=>'TGJU'];
        set_transient($cache_key,$data,max(60,(int)$s['refresh_minutes']*MINUTE_IN_SECONDS));
        update_option($cache_key.'_last',$data,false);
        return $data;
    }

    private function fetch_crypto() {
        $s=$this->settings();
        $key='gheymatbar_crypto_prices';
        $cached=get_transient($key);
        if(is_array($cached)) return $cached;

        $url='https://api.coingecko.com/api/v3/simple/price?ids=bitcoin,ethereum,tether&vs_currencies=usd';
        $res=wp_remote_get($url,['timeout'=>15,'headers'=>['Accept'=>'application/json','User-Agent'=>'Gheymatbar/'.self::VERSION]]);
        if(is_wp_error($res)||200!==(int)wp_remote_retrieve_response_code($res)){
            $fallback=get_option($key.'_last');
            return is_array($fallback)?$fallback:[];
        }
        $j=json_decode(wp_remote_retrieve_body($res),true);
        if(!is_array($j)) return [];
        $data=[
            'Bitcoin'=>isset($j['bitcoin']['usd'])?(float)$j['bitcoin']['usd']:null,
            'Ethereum'=>isset($j['ethereum']['usd'])?(float)$j['ethereum']['usd']:null,
            'Tether'=>isset($j['tether']['usd'])?(float)$j['tether']['usd']:null,
        ];
        $data=array_filter($data,function($v){return null!==$v&&$v>0;});
        set_transient($key,$data,max(60,(int)$s['refresh_minutes']*MINUTE_IN_SECONDS));
        update_option($key.'_last',$data,false);
        return $data;
    }

    private function format_market_value($data,$convert_toman=true) {
        if(is_wp_error($data)||empty($data['value'])) return 'ناموجود';
        $value=(float)$data['value'];
        if($convert_toman) $value=$value/10;
        return number_format_i18n($value,0).' '.($convert_toman?'تومان':'ریال');
    }

    private function render_box($title, $items) {
        $s = $this->settings();
        $classes = 'gheymatbar-box gheymatbar-' . esc_attr($s['layout']) . ' gheymatbar-theme-' . esc_attr($s['theme']);
        ob_start(); ?>
        <div class="<?php echo esc_attr($classes); ?>" style="--gheymatbar-accent:<?php echo esc_attr($s['accent']); ?>">
            <div class="gheymatbar-title"><?php echo esc_html($title); ?></div>
            <div class="gheymatbar-items">
                <?php foreach ($items as $label => $value): ?>
                    <div class="gheymatbar-item"><span><?php echo esc_html($label); ?></span><strong><?php echo esc_html($value); ?></strong></div>
                <?php endforeach; ?>
            </div>
            <small><?php echo esc_html($s['source_note']); ?> · بروزرسانی هر <?php echo esc_html($s['refresh_minutes']); ?> دقیقه</small>
        </div>
        <style>
            .gheymatbar-box{border:1px solid #e5e7eb;border-radius:18px;padding:16px;margin:12px 0;background:#fff}.gheymatbar-title{font-weight:700;margin-bottom:12px}.gheymatbar-items{display:flex;gap:10px;flex-wrap:wrap}.gheymatbar-vertical .gheymatbar-items{display:grid;grid-template-columns:1fr}.gheymatbar-item{border:1px solid #e5e7eb;border-radius:12px;padding:10px 12px;min-width:120px}.gheymatbar-item span{display:block;font-size:12px;color:#6b7280}.gheymatbar-item strong{display:block;margin-top:4px;color:var(--gheymatbar-accent)}.gheymatbar-theme-dark{background:#111827;color:#fff;border-color:#1f2937}.gheymatbar-theme-dark .gheymatbar-item{border-color:#374151}.gheymatbar-theme-glass{background:rgba(255,255,255,.72);backdrop-filter:blur(14px)}
        </style>
        <?php return ob_get_clean();
    }

    public function gold_shortcode() {
        return $this->render_box('طلا',[
            'طلای ۱۸ عیار'=>$this->format_market_value($this->fetch_tgju('geram18')),
            'طلای ۲۴ عیار'=>$this->format_market_value($this->fetch_tgju('geram24')),
        ]);
    }

    public function coin_shortcode() {
        return $this->render_box('سکه',[
            'سکه امامی'=>$this->format_market_value($this->fetch_tgju('sekee')),
            'نیم سکه'=>$this->format_market_value($this->fetch_tgju('nim')),
            'ربع سکه'=>$this->format_market_value($this->fetch_tgju('rob')),
        ]);
    }

    public function currency_shortcode() {
        return $this->render_box('ارز',[
            'دلار'=>$this->format_market_value($this->fetch_tgju('price_dollar_rl')),
            'یورو'=>$this->format_market_value($this->fetch_tgju('price_eur')),
            'درهم'=>$this->format_market_value($this->fetch_tgju('price_aed')),
        ]);
    }

    public function crypto_shortcode() {
        $c=$this->fetch_crypto();
        return $this->render_box('رمزارز',[
            'Bitcoin'=>isset($c['Bitcoin'])?'$'.number_format_i18n($c['Bitcoin'],2):'ناموجود',
            'Ethereum'=>isset($c['Ethereum'])?'$'.number_format_i18n($c['Ethereum'],2):'ناموجود',
            'Tether'=>isset($c['Tether'])?'$'.number_format_i18n($c['Tether'],4):'ناموجود',
        ]);
    }

    public function all_shortcode() {
        return $this->gold_shortcode().$this->coin_shortcode().$this->currency_shortcode().$this->crypto_shortcode();
    }

}

new Gheymatbar_Plugin();
