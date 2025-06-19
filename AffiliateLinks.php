<?php
if (!defined('IN_MYBB')) {
    die('Direct access not allowed.');
}

// Some info about the plugin
function affiliatelinks_info()
{
    return [
        'name' => 'AffiliateLinks',
        'description' => 'Automatically adds or replaces affiliate tags in posts, PMs, signatures, and profiles.',
        'website' => '',
        'author' => 'Richard Pinnock-Edmonds',
        'authorsite' => 'https://www.richedmonds.co.uk/',
        'version' => '0.1',
        'compatibility' => '18*'
    ];
}

// These are the primary hooks
$plugins->add_hook('postbit', 'check_affiliate_links');
$plugins->add_hook('postbit_prev', 'check_affiliate_links');
$plugins->add_hook('private_message', 'check_affiliate_links');
$plugins->add_hook('member_profile_end', 'check_affiliate_links_profile');
$plugins->add_hook('parse_message_end', 'check_affiliate_links_parse');
$plugins->add_hook('admin_config_settings_change_commit', 'affiliatelinks_cache');

// Check for affiliate links in posts and messages
function check_affiliate_links(&$post)
{
    $post['message'] = affiliate_links_add_tags($post['message']);
    if (isset($post['signature'])) {
        $post['signature'] = affiliate_links_add_tags($post['signature']);
    }
}

// Check for affiliate links in profiles
function check_affiliate_links_profile()
{
    global $memprofile;
    if (isset($memprofile['bio'])) {
        $memprofile['bio'] = affiliate_links_add_tags($memprofile['bio']);
    }
}

function check_affiliate_links_parse(&$message)
{
    $message = affiliate_links_add_tags($message);
}

// Where the magic takes place
function affiliate_links_add_tags($message)
{
    global $mybb, $cache;

    $rules = $cache->read('affiliatelinks_rules');
    if (!is_array($rules)) {
        affiliatelinks_cache(); // rebuild if missing
        $rules = $cache->read('affiliatelinks_rules');
    }

    $overwrite = (int) $mybb->settings['affiliatelinks_overwrite'];

    $message = preg_replace_callback(
        '#<a\s[^>]*href=[\'"]([^\'"]+)[\'"][^>]*>(.*?)</a>#i',
        function ($matches) use ($rules, $overwrite) {
            $url = $matches[1];
            $text = $matches[2];

            $parsed_url = parse_url($url);
            if (!$parsed_url || !isset($parsed_url['host'])) return $matches[0];

            foreach ($rules as $domain => $tag) {
                if (stripos($parsed_url['host'], $domain) !== false) {
                    $query = isset($parsed_url['query']) ? $parsed_url['query'] : '';
                    parse_str($query, $query_array);
                    parse_str($tag, $tag_array);

                    // If overwrite is enabled, remove existing tag keys
                    if ($overwrite) {
                        foreach (array_keys($tag_array) as $key) {
                            unset($query_array[$key]);
                        }
                    } else {
                        // Skip if any tag key already exists
                        $conflict = false;
                        foreach (array_keys($tag_array) as $key) {
                            if (isset($query_array[$key])) {
                                $conflict = true;
                                break;
                            }
                        }
                        if ($conflict) return $matches[0];
                    }

                    // Add new tag(s)
                    $query_array = array_merge($query_array, $tag_array);
                    $new_query = http_build_query($query_array);

                    // Rebuild URL
                    $scheme   = $parsed_url['scheme'] ?? 'https';
                    $host     = $parsed_url['host'];
                    $path     = $parsed_url['path'] ?? '';
                    $fragment = isset($parsed_url['fragment']) ? '#' . $parsed_url['fragment'] : '';

                    $url = "{$scheme}://{$host}{$path}?" . $new_query . $fragment;
                    break;
                }
            }

            return '<a href="' . htmlspecialchars($url) . '">' . $text . '</a>';
        },
        $message
    );

    return $message;
}

// Installing the plugin
function affiliate_links_install()
{
    global $db;

    $group = [
        'name' => 'affiliate_links',
        'title' => 'Affiliate Link Settings',
        'description' => 'Settings for the Affiliate Link Tagger plugin',
        'disporder' => 100,
        'isdefault' => 0
    ];
    $gid = $db->insert_query('settinggroups', $group);

    // Add some settings to the Admin CP to make it easier to configure
    $settings = [
        [
            'name' => 'affiliate_links_domains',
            'title' => 'Affiliate Domains & Tags',
            'description' => 'Format: one per line (e.g., amazon.com=tag=mytag-20)',
            'optionscode' => 'textarea',
            'value' => "amazon.com=tag=mytag-20\nexample.com=ref=yourid",
            'disporder' => 1,
            'gid' => $gid
        ],
        [
            'name' => 'affiliatelinks_overwrite',
            'title' => 'Overwrite Existing Tags',
            'description' => 'Should existing affiliate tags be overwritten?',
            'optionscode' => 'yesno',
            'value' => 0,
            'disporder' => 2,
            'gid' => $gid
        ]
    ];

    foreach ($settings as $setting) {
        $db->insert_query('settings', $setting);
    }

    rebuild_settings();
    affiliatelinks_cache();
}

// Uninstalling the plugin
function affiliate_links_uninstall()
{
    global $db;
    $db->delete_query('settings', "name IN ('affiliate_links_domains','affiliatelinks_overwrite')");
    $db->delete_query('settinggroups', "name='affiliate_links'");
    $cache = new datacache;
    $cache->delete('affiliatelinks_rules');

    rebuild_settings();
}

// Building the cache to improve performance
function affiliatelinks_cache()
{
    global $mybb, $cache;

    $settings_raw = $mybb->settings['affiliate_links_domains'];
    $rules = [];

    if (!empty($settings_raw)) {
        $lines = explode("\n", $settings_raw);
        foreach ($lines as $line) {
            $line = trim($line);
            if (strpos($line, '=') !== false) {
                [$domain, $tag] = explode('=', $line, 2);
                $rules[trim($domain)] = trim($tag);
            }
        }
    }

    $cache->update('affiliatelinks_rules', $rules);
}

// Activating and deactivting the plugin
function affiliate_links_activate() {}
function affiliate_links_deactivate() {}
