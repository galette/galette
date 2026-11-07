<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Navigation;

use Galette\Tests\GaletteTestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

use function Safe\realpath;

/**
 * News feed tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class NewsFeedTest extends GaletteTestCase
{
    protected int $seed = 20230324120838;
    protected bool $load_plugins = true;

    /**
     * News feed built from the globals tests replace with mocks
     *
     * @phpstan-impure
     */
    private function newsFeed(): \Galette\Navigation\NewsFeed
    {
        global $login, $preferences, $plugins;
        return new \Galette\Navigation\NewsFeed($login, $preferences, $plugins);
    }

    /**
     * Test $this->newsFeed()->getEntries() method
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testGetNews(): void
    {
        $entries = $this->newsFeed()->getEntries();
        $this->assertCount(0, $entries);

        $this->login->logAdmin('superadmin', $this->preferences);
        $this->assertTrue($this->login->isLogged());
        $this->assertTrue($this->login->isSuperAdmin());

        //we got only Galette News
        $entries = $this->newsFeed()->getEntries();
        $this->assertCount(1, $entries);
        $entry = array_pop($entries);
        $this->assertInstanceOf(\Galette\IO\News\Entry::class, $entry);
        $this->assertNotEmpty($entry->getTitle());
        $this->assertSame(50, $entry->getPosition());
        $posts = $entry->getPosts();
        $this->assertGreaterThan(0, count($posts));
        $post = array_pop($posts);
        $this->assertInstanceOf(\Galette\IO\News\Post::class, $post);
        $this->assertNotEmpty($post->getTitle());
        $this->assertNotEmpty($post->getUrl());
        $this->assertNotEmpty($post->getDate());

        $this->preferences->pref_rss_url = 'file:///' . realpath(GALETTE_ROOT . '../tests/feed.xml');
        $this->assertTrue($this->preferences->store());

        //we got both Galette and asso news
        $entries = $this->newsFeed()->getEntries();

        //reset
        $this->preferences->pref_rss_url = \Galette\Core\Galette::RSS_URL;
        $this->assertTrue($this->preferences->store());

        $this->assertCount(2, $entries);

        $this->login->logOut();

        $this->getMemberOne();
        $mdata = $this->dataAdherentOne();
        $this->assertTrue($this->login->logIn($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertFalse($this->login->isAdmin());
        $this->assertFalse($this->login->isStaff());

        //no news to display
        $entries = $this->newsFeed()->getEntries();
        $this->assertCount(0, $entries);

        $this->preferences->pref_rss_url = 'file:///' . realpath(GALETTE_ROOT . '../tests/feed.xml');
        $this->assertTrue($this->preferences->store());

        //we got asso news
        $entries = $this->newsFeed()->getEntries();

        //reset
        $this->preferences->pref_rss_url = \Galette\Core\Galette::RSS_URL;
        $this->assertTrue($this->preferences->store());

        $this->assertCount(1, $entries);

        $this->plugins
            ->setContainer($this->container)
            ->loadModules($this->preferences, GALETTE_PLUGINS_PATH);
        $this->plugins->activateModule('plugin-news');

        $this->plugins = new \Galette\Core\Plugins();
        $this->plugins
            ->setContainer($this->container)
            ->loadModules($this->preferences, GALETTE_PLUGINS_PATH);

        $this->assertArrayNotHasKey('plugin-news', $this->plugins->getDisabledModules());

        global $plugins, $container;
        $plugins = $this->plugins;

        //mock plugin to mark as not installed
        /** @var class-string<\Galette\Core\GalettePlugin> $plugin_class */
        $plugin_class = $plugins->getClassName('plugin-news', full: true);
        $mock = $this->getMockBuilder($plugin_class)
            ->onlyMethods(['isInstalled'])
            ->getMock();
        $mock->method('isInstalled')->willReturn(false);
        $container->set($plugin_class, $mock);

        //we got asso and plugin news
        $entries = $this->newsFeed()->getEntries();

        //by default, plugin-news is marked as not installed, so we don't get it news
        $this->assertCount(0, $entries);

        //mock plugin to mark as installed
        /** @var class-string<\Galette\Core\GalettePlugin> $plugin_class */
        $plugin_class = $plugins->getClassName('plugin-news', full: true);
        $mock = $this->getMockBuilder($plugin_class)
            ->onlyMethods(['isInstalled'])
            ->getMock();
        $mock->method('isInstalled')->willReturn(true);
        $container->set($plugin_class, $mock);

        $entries = $this->newsFeed()->getEntries();

        //reset
        $this->plugins->deactivateModule('plugin-news');
        $container->set($plugin_class, new $plugin_class());

        $this->assertCount(1, $entries);
        $entry = array_pop($entries);
        $this->assertInstanceOf(\Galette\IO\News\Entry::class, $entry);
        $this->assertSame('Test plugin news', $entry->getTitle());
        $this->assertSame(42, $entry->getPosition());
        $posts = $entry->getPosts();
        $this->assertCount(2, $posts);
        $post = array_pop($posts);
        $this->assertInstanceOf(\Galette\IO\News\Post::class, $post);
        $this->assertNotEmpty($post->getTitle());
        $this->assertNull($post->getUrl());
        $this->assertNotEmpty($post->getDate());
    }

    /**
     * Test $this->newsFeed()->hasEntries() method
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testHasNews(): void
    {
        //not logged in, no custom feed, no plugin: nothing to expect
        $this->assertFalse($this->newsFeed()->hasEntries());

        //staff and admins get Galette news
        $this->login->logAdmin('superadmin', $this->preferences);
        $this->assertTrue($this->newsFeed()->hasEntries());
        $this->login->logOut();

        //a simple member gets nothing...
        $this->getMemberOne();
        $mdata = $this->dataAdherentOne();
        $this->assertTrue($this->login->logIn($mdata['login_adh'], $mdata['mdp_adh']));
        $this->assertFalse($this->newsFeed()->hasEntries());

        //...unless the association has its own feed
        $this->preferences->pref_rss_url = 'file:///' . realpath(GALETTE_ROOT . '../tests/feed.xml');
        $this->assertTrue($this->preferences->store());
        $this->assertTrue($this->newsFeed()->hasEntries());

        //reset
        $this->preferences->pref_rss_url = \Galette\Core\Galette::RSS_URL;
        $this->assertTrue($this->preferences->store());
        $this->assertFalse($this->newsFeed()->hasEntries());

        $this->plugins
            ->setContainer($this->container)
            ->loadModules($this->preferences, GALETTE_PLUGINS_PATH);
        $this->plugins->activateModule('plugin-news');

        $this->plugins = new \Galette\Core\Plugins();
        $this->plugins
            ->setContainer($this->container)
            ->loadModules($this->preferences, GALETTE_PLUGINS_PATH);

        global $plugins, $container;
        $plugins = $this->plugins;

        /** @var class-string<\Galette\Core\GalettePlugin> $plugin_class */
        $plugin_class = $plugins->getClassName('plugin-news', full: true);

        //a plugin that is not installed provides nothing
        $mock = $this->getMockBuilder($plugin_class)
            ->onlyMethods(['isInstalled'])
            ->getMock();
        $mock->method('isInstalled')->willReturn(false);
        $container->set($plugin_class, $mock);
        $this->assertFalse($this->newsFeed()->hasEntries());

        //an installed one does
        $mock = $this->getMockBuilder($plugin_class)
            ->onlyMethods(['isInstalled'])
            ->getMock();
        $mock->method('isInstalled')->willReturn(true);
        $container->set($plugin_class, $mock);
        $news = $this->newsFeed();
        $has_news = $news->hasEntries();

        //reset
        $this->plugins->deactivateModule('plugin-news');
        $container->set($plugin_class, new $plugin_class());

        $this->assertTrue($has_news);
    }
}
