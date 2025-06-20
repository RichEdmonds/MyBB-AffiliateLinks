<?php
/**
 * AffiliateLinks Plugin for MyBB 1.8.x
 * Automatically appends affiliate tags to eligible outbound URLs.
 * Author: Richard Pinnock-Edmonds
 * Website: https://www.richedmonds.co.uk/
 * Version: 0.2
 */

if (!defined("IN_MYBB")) {
    die("Direct initialization of this file is not allowed.");
}

// Info for the plugin so MyBB can see what it does
function affiliatelinks_info()
{
    return [
        "name" => "AffiliateLinks",
        "description" => "Automatically adds affiliate tags to eligible links in posts.",
        "website" => "https://www.richedmonds.co.uk/",
        "author" => "Richard Pinnock-Edmonds",
        "authorsite" => "https://www.richedmonds.co.uk/",
        "version" => "0.2",
        "compatibility" => "18*"
    ];
}

// Supported domains list, you can add some more domains here that will show in the ACP
function affiliatelinks_supported_domains()
{
    return [
        'amazon.com' => 'Amazon US',
        'ebay.com' => 'eBay US',
        'example.com' => 'Example'
    ];
}

// Let's activate the plugin in MyBB, shall we?
function affiliatelinks_activate()
{
    global $db;

    $group = [
        'name' => 'affiliatelinks',
        'title' => 'Affiliate Links Settings',
        'description' => 'Settings for the AffiliateLinks plugin.',
        'disporder' => 1,
        'isdefault' => 0
    ];
    $gid = $db->insert_query('settinggroups', $group);

    $settings = [
        [
            'name' => 'affiliatelinks_enabled',
            'title' => 'Enable AffiliateLinks',
            'description' => 'Enable or disable the AffiliateLinks plugin.',
            'optionscode' => 'onoff',
            'value' => '1',
            'disporder' => 1,
            'gid' => $gid,
        ],
        [
            'name' => 'affiliatelinks_nofollow',
            'title' => 'Add rel="nofollow"',
            'description' => 'Automatically add rel="nofollow" to affiliate links?',
            'optionscode' => 'onoff',
            'value' => '1',
            'disporder' => 2,
            'gid' => $gid,
        ]
    ];

    $disporder = 10;
    foreach (affiliatelinks_supported_domains() as $domain => $label) {
        $settings[] = [
            'name' => 'affiliatelinks_params_' . str_replace('.', '_', $domain),
            'title' => "$label Affiliate Parameters",
            'description' => "Parameters to append to $domain links (e.g., tag=xyz). Leave blank to disable.",
            'optionscode' => 'text',
            'value' => '',
            'disporder' => $disporder++,
            'gid' => $gid,
        ];
    }

    foreach ($settings as $setting) {
        $db->insert_query('settings', $setting);
    }

    rebuild_settings();
}

// Sorry to see you go, but here's how we deactivate the plugin
function affiliatelinks_deactivate()
{
    global $db;

    $names = ['affiliatelinks_enabled', 'affiliatelinks_nofollow'];

    foreach (affiliatelinks_supported_domains() as $domain => $label) {
        $names[] = 'affiliatelinks_params_' . str_replace('.', '_', $domain);
    }

    $escaped_names = array_map([$db, 'escape_string'], $names);
    $name_list = "'" . implode("','", $escaped_names) . "'";
    $db->delete_query('settings', "name IN ($name_list)");
    $db->delete_query('settinggroups', "name = 'affiliatelinks'");
    rebuild_settings();
}

// Some additional hooks to make the magic gappen
$plugins->add_hook("postbit", "affiliatelinks_process");
$plugins->add_hook("postbit_announcement", "affiliatelinks_process");

// Primary processing for the plugin
function affiliatelinks_process(&$post)
{
    global $mybb;

    if ($mybb->settings['affiliatelinks_enabled'] != 1) {
        return;
    }

    $post['message'] = affiliatelinks_modify_links($post['message']);
}

// Let's see what domains have been cached by MyBB for processing
function affiliatelinks_get_cached_domains()
{
    global $mybb;
    static $cached = null;

    if ($cached !== null) {
        return $cached;
    }

    $cached = [];

    foreach (affiliatelinks_supported_domains() as $domain => $label) {
        $key = 'affiliatelinks_params_' . str_replace('.', '_', $domain);
        if (!empty($mybb->settings[$key])) {
            $cached[$domain] = $mybb->settings[$key];
        }
    }

    return $cached;
}

// Now it's time to bring in the link modifier
function affiliatelinks_modify_links($message)
{
    global $mybb;

    $affiliate_domains = affiliatelinks_get_cached_domains();

    return preg_replace_callback(
        '#<a\s([^>]*?)href=["\']([^"\']+)["\']([^>]*)>(.*?)</a>#is',
        function ($matches) use ($affiliate_domains, $mybb) {
            $before = $matches[1];
            $url = $matches[2];
            $after = $matches[3];
            $link_text = $matches[4];

            foreach ($affiliate_domains as $domain => $param) {
                if (strpos($url, $domain) !== false && strpos($url, $param) === false) {
                    $parsed_url = parse_url($url);
                    parse_str($parsed_url['query'] ?? '', $query);
                    parse_str($param, $param_array);
                    $query = array_merge($query, $param_array);
                    $parsed_url['query'] = http_build_query($query);
                    $new_url = (isset($parsed_url['scheme']) ? $parsed_url['scheme'] . '://' : '') .
                        ($parsed_url['host'] ?? '') .
                        ($parsed_url['path'] ?? '') .
                        (!empty($parsed_url['query']) ? '?' . $parsed_url['query'] : '') .
                        (isset($parsed_url['fragment']) ? '#' . $parsed_url['fragment'] : '');

                    // rel="nofollow" logic
                    if ($mybb->settings['affiliatelinks_nofollow']) {
                        if (preg_match('/rel=["\']([^"\']*)["\']/i', $before . $after, $rel_match)) {
                            $rel_value = $rel_match[1];
                            if (!str_contains($rel_value, 'nofollow')) {
                                $new_rel = 'rel="' . trim($rel_value . ' nofollow') . '"';
                                $before = preg_replace('/rel=["\'][^"\']*["\']/', $new_rel, $before . $after, 1);
                                $after = '';
                            }
                        } else {
                            $after .= ' rel="nofollow"';
                        }
                    }

                    return '<a ' . $before . 'href="' . $new_url . '"' . $after . '>' . $link_text . '</a>';
                }
            }

            return $matches[0];
        },
        $message
    );
}
