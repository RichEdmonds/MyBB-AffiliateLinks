<h1 align="center">AffiliateLinks</h1>
<h3 align="center">A MyBB plugin for handling affiliate links</h3>

---

AffiliateLinks is a free MyBB plugin for handling affiliate link rewriting across the entire bulletin board. This plugin will alter links published within signatures and posts to add configured tags to ensure clicks are registered on forum owner affiliate accounts.

For details on how to install and configure this plugin, please see this readme documentation. AffiliateLinks is available right here on GitHub as well as through the official MyBB plugin repository, making it easy to install the plugin without leaving your forum's Admin CP.

<strong>Getting started</strong><br/>
Download AffiliateLinks through the <a href="">MyBB repository</a> on your forum or copy the files manually from GitHub.<br/>

<strong>Spotted something out of place?</strong><br/>
Open an <a href="https://github.com/RichEdmonds/MyBB-AffiliateLinks/issues">Issue</a> right here on GitHub.<br/>

---

## Installing AffiliateLinks

This repository contains the necessary files for installing AffiliateLinks on your MyBB forum.

1. Upload <strong>AffiliateLinks</strong> to your MyBB plugins folder (or download it through the Admin CP).

    Add MyBB plugins to <code>inc/plugins/</code>.
2. Log in to your MyBB Admin CP.
3. Go to <strong>Configuration > Plugins</strong>.
4. Click <strong>Install & Activate</strong> for AffiliateLinks.

### Prerequisites

AffiliateLinks doesn't require a specific MyBB version for installation to take place, though I always recommend using the latest release. This ensures you're rocking the most recent security patches.

## Configuring AffiliateLinks

Configuring AffiliateLinks is straightforward, and there are only a few settings to manage.

1. Click <strong>Settings</strong> in the side menu.
2. Scroll down to <strong>AffiliateLink Settings</strong>.
3. Add all your <strong>affiliated domains and tags</strong>:

    <code>amazon.com=tag=mytag-20</code>
    <code>example.com=ref=affiliateid</code>

4. Configure <strong>Overwrite Existing Tags</strong>:

    <strong>Yes:</strong> Replace all existing tags, overwriting them. (Will overwrite all tags added by members.)<br/>
    <strong>No:</strong> Only add the tag if nothing exists, leaving those already appended. (Will skip any added by members.)
