<?php

/***************************************************************************
 *
 *  AffiliateLinks MyBB Plugin (/inc/plugins/affiliatelinks.php)
 *  Author: Richard Pinnock-Edmonds
 *  © 2025 Richard Pinnock-Edmonds
 *  
 *  License: GPL-3.0
 *
 *  This plugin allows MyBB administrators to append affiliate tags and codes to URLs.
 *
 ***************************************************************************/

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
        'compatibility' => '18*',
        'codename' => 'affiliatelinks',  // Codename should match the plugin file name
        'settings' => 'affiliatelinks',  // This links to the settings group
        'guid' => '', // Optional for now, can be added later for unique plugin identification
    ];
}

// Check for affiliate links in posts and messages
function affiliatelinks(&$post)
{
    $post['message'] = affiliatelinks_add_tags($post['message']);
    if (isset($post['signature'])) {
        $post['signature'] = affiliatelinks_add_tags($post['signature']);
    }
}

// Check for affiliate links in profiles
function affiliatelinks_profile()
{
    global $memprofile;
    if (isset($memprofile['bio'])) {
        $memprofile['bio'] = affiliatelinks_add_tags($memprofile['bio']);
    }
}

function affiliatelinks_parse(&$message)
{
    $message = affiliatelinks_add_tags($message);
}

// Where the magic takes place
function affiliatelinks_add_tags($message)
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

            return '<a href="' . htmlspecialchars($url) . '">' . htmlspecialchars($text) . '</a>';
        },
        $message
    );

    return $message;
}

function affiliatelinks_install()
{
    global $db;

    // Install plugin settings
    $setting_group = [
        'name' => 'affiliatelinks',
        'title' => 'Affiliate Link Settings',
        'description' => 'Settings for the Affiliate Link Tagger plugin',
        'disporder' => 50,
        'isdefault' => 0
    ];

    $gid = $db->insert_query('settinggroups', $setting_group);

    // Adding settings
    $setting_array = [
        [
            'name' => 'affiliatelinks_domains',
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

    foreach ($setting_array as $setting) {
        $db->insert_query('settings', $setting);
    }

    rebuild_settings();
}

// Uninstalling the plugin
function affiliatelinks_uninstall()
{
    global $db, $cache;

    // Safely delete settings if they exist
    if ($db->fetch_field($db->simple_select('settings', 'sid', "name='affiliatelinks_domains'"), 'sid')) {
        $db->delete_query('settings', "name IN ('affiliatelinks_domains', 'affiliatelinks_overwrite')");
    }
    if ($db->fetch_field($db->simple_select('settinggroups', 'gid', "name='affiliatelinks'"), 'gid')) {
        $db->delete_query('settinggroups', "name='affiliatelinks'");
    }

    // Clear cache
    $cache->delete('affiliatelinks_rules');

    rebuild_settings();
}

// Building the cache to improve performance
function affiliatelinks_cache()
{
    global $mybb, $cache;

    $settings_raw = $mybb->settings['affiliatelinks_domains'];
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

// Check to see if the plugin is installed
function affiliatelinks_is_installed()
{
    global $db;

    $group = $db->fetch_field(
        $db->simple_select('settinggroups', 'gid', "name='affiliatelinks'"),
        'gid'
    );

    if (!$group) return false;

    $query = $db->simple_select('settings', 'sid', "name='affiliatelinks_domains'");
    return ($db->num_rows($query) > 0);
}

// Deactivting the plugin
function affiliatelinks_deactivate()
{
    global $db, $cache;

    // Remove plugin-related cache
    $cache->delete('affiliatelinks_rules');

    // Optionally, remove settings from the database (if you want complete cleanup)
    //$db->delete_query('settings', "name IN ('affiliatelinks_domains', 'affiliatelinks_overwrite')");
    //$db->delete_query('settinggroups', "name='affiliatelinks'");
}

function affiliatelinks_activate()
{
    global $plugins;

    // These hooks should only be added after the plugin is activated and settings are properly saved
    $plugins->add_hook('postbit', 'affiliatelinks');
    $plugins->add_hook('postbit_prev', 'affiliatelinks');
    $plugins->add_hook('private_message', 'affiliatelinks');
    $plugins->add_hook('member_profile_end', 'affiliatelinks_profile');
    $plugins->add_hook('parse_message_end', 'affiliatelinks_parse');
    $plugins->add_hook('acp_settings_change_commit', 'affiliatelinks_cache');
}

