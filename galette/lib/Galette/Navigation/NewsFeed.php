<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Navigation;

use Galette\Core\Galette;
use Galette\Core\Login;
use Galette\Core\Plugins;
use Galette\Core\Preferences;
use Galette\IO\News;

/**
 * News of Galette, of the association and of plugins
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class NewsFeed
{
    /**
     * Constructor
     *
     * @param Login       $login       Login instance
     * @param Preferences $preferences Preferences instance
     * @param Plugins     $plugins     Plugins instance
     */
    public function __construct(
        private readonly Login $login,
        private readonly Preferences $preferences,
        private readonly Plugins $plugins
    ) {
    }

    /**
     * Get Galette news
     *
     * @return array<int, News\Entry>
     */
    public function getEntries(): array
    {
        $news = [];

        //display Galette news for staff and admins
        if ($this->login->isStaff() || $this->login->isAdmin()) {
            $core_news = new News(Galette::RSS_URL);
            $entry = new News\Entry(
                _T("Galette news"),
                $core_news->getPosts(),
                50
            );
            $news[$entry->getPosition()] = $entry;
        }
        //if a custom RSS feed is set, we add it for everyone; and before Galette news
        if (!empty($this->preferences->pref_rss_url) && $this->preferences->pref_rss_url != Galette::RSS_URL) {
            $asso_news = new News($this->preferences->pref_rss_url);
            $entry = new News\Entry(
                _T("Association news"),
                $asso_news->getPosts(),
                20
            );
            $news[$entry->getPosition()] = $entry;
        }

        foreach ($this->plugins->getProviders(Plugins\NewsProviderInterface::class) as $plugin) {
            $entry = $plugin->getNews();
            if ($entry === null) {
                continue;
            }

            $position = $entry->getPosition();
            while (isset($news[$position])) {
                ++$position;
            }
            $news[$position] = $entry;
        }

        ksort($news);
        return $news;
    }

    /**
     * Are news to be expected?
     *
     * Tells whether a news source is configured, without loading any feed:
     * getEntries() reaches the network, this one must not. A plugin may still
     * provide no entry at all, so the answer is optimistic.
     */
    public function hasEntries(): bool
    {
        //Galette news are displayed for staff and admins
        if ($this->login->isStaff() || $this->login->isAdmin()) {
            return true;
        }

        //a custom RSS feed is displayed for everyone
        if (!empty($this->preferences->pref_rss_url) && $this->preferences->pref_rss_url != Galette::RSS_URL) {
            return true;
        }

        return $this->plugins->getProviders(Plugins\NewsProviderInterface::class) !== [];
    }
}
