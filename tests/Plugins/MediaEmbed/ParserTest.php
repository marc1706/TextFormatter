<?php

namespace s9e\TextFormatter\Tests\Plugins\MediaEmbed;

use s9e\TextFormatter\Configurator;
use s9e\TextFormatter\Parser\Tag;
use s9e\TextFormatter\Plugins\MediaEmbed\Parser;
use s9e\TextFormatter\Tests\Plugins\ParsingTestsRunner;
use s9e\TextFormatter\Tests\Plugins\ParsingTestsJavaScriptRunner;
use s9e\TextFormatter\Tests\Plugins\RenderingTestsRunner;
use s9e\TextFormatter\Tests\Test;

/**
* @covers s9e\TextFormatter\Plugins\MediaEmbed\Parser
*/
class ParserTest extends Test
{
	use ParsingTestsRunner;
	use ParsingTestsJavaScriptRunner;
	use RenderingTestsRunner;

	protected static function populateCache($entries)
	{
		$cacheDir = __DIR__ . '/../../.cache';

		if (!file_exists($cacheDir))
		{
			$cacheDir = sys_get_temp_dir();
		}

		$prefix = $suffix = '';
		if (extension_loaded('zlib'))
		{
			$prefix  = 'compress.zlib://';
			$suffix  = '.gz';
		}

		foreach ($entries as $url => $content)
		{
			file_put_contents(
				$prefix . $cacheDir . '/http.' . crc32($url) . $suffix,
				$content
			);
		}

		return $cacheDir;
	}

	/**
	* @testdox scrape() does not do anything if the tag does not have a "url" attribute
	*/
	public function testScrapeNoUrl()
	{
		$tag = new Tag(Tag::START_TAG, 'MEDIA', 0, 0);

		$this->assertTrue(Parser::scrape($tag, []));
	}

	/**
	* @testdox The [MEDIA] tag transfers its priority to the tag it creates
	*/
	public function testTagPriority()
	{
		$newTag = $this->getMockBuilder('s9e\\TextFormatter\\Parser\\Tag')
		               ->disableOriginalConstructor()
		               ->setMethods(['setAttributes', 'setSortPriority'])
		               ->getMock();

		$newTag->expects($this->once())
		       ->method('setSortPriority')
		       ->with(123);

		$tagStack = $this->getMockBuilder('s9e\\TextFormatter\\Parser')
		                 ->disableOriginalConstructor()
		                 ->setMethods(['addSelfClosingTag'])
		                 ->getMock();

		$tagStack->expects($this->once())
		         ->method('addSelfClosingTag')
		         ->will($this->returnValue($newTag));

		$tag = new Tag(Tag::START_TAG, 'MEDIA', 0, 0);
		$tag->setAttribute('media', 'foo');
		$tag->setSortPriority(123);

		Parser::filterTag($tag, $tagStack, []);
	}

	/**
	* @testdox Abstract tests (not tied to bundled sites)
	* @dataProvider getAbstractTests
	*/
	public function testAbstract()
	{
		call_user_func_array([$this, 'testParsing'], func_get_args());
	}

	public function getAbstractTests()
	{
		return [
			[
				// Multiple "match" in scrape
				'http://example.invalid/123',
				'<r><EXAMPLE id="456" url="http://example.invalid/123">http://example.invalid/123</EXAMPLE></r>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = self::populateCache([
						'http://example.invalid/123' => '456'
					]);

					$configurator->MediaEmbed->add(
						'example',
						[
							'host'    => 'example.invalid',
							'scrape'  => [
								'match'   => ['/XXX/', '/123/'],
								'extract' => "!^(?'id'[0-9]+)$!"
							],
							'template' => ''
						]
					);
				}
			],
			[
				// Multiple "extract" in scrape
				'http://example.invalid/123',
				'<r><EXAMPLE id="456" url="http://example.invalid/123">http://example.invalid/123</EXAMPLE></r>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = self::populateCache([
						'http://example.invalid/123' => '456'
					]);

					$configurator->MediaEmbed->add(
						'example',
						[
							'host'    => 'example.invalid',
							'scrape'  => [
								'match'   => '/./',
								'extract' => ['/foo/', "!^(?'id'[0-9]+)$!"]
							],
							'template' => ''
						]
					);
				}
			],
			[
				// Multiple scrapes
				'http://example.invalid/123',
				'<r><EXAMPLE id="456" url="http://example.invalid/123">http://example.invalid/123</EXAMPLE></r>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = self::populateCache([
						'http://example.invalid/123' => '456'
					]);

					$configurator->MediaEmbed->add(
						'example',
						[
							'host'    => 'example.invalid',
							'scrape'  => [
								[
									'match'   => '/./',
									'extract' => '/foo/'
								],
								[
									'match'   => '/./',
									'extract' => "!^(?'id'[0-9]+)$!"
								]
							],
							'template' => ''
						]
					);
				}
			],
		];
	}

	/**
	* @testdox Scraping tests
	* @dataProvider getScrapingTests
	* @group needs-network
	*/
	public function testScraping()
	{
		call_user_func_array([$this, 'testParsing'], func_get_args());
	}

	public function getScrapingTests()
	{
		return [
			[
				'http://proleter.bandcamp.com/album/curses-from-past-times-ep',
				'<r><BANDCAMP album_id="1122163921" url="http://proleter.bandcamp.com/album/curses-from-past-times-ep">http://proleter.bandcamp.com/album/curses-from-past-times-ep</BANDCAMP></r>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('bandcamp');
				}
			],
			[
				'http://proleter.bandcamp.com/track/muhammad-ali',
				'<r><BANDCAMP album_id="1122163921" track_id="3496015802" track_num="7" url="http://proleter.bandcamp.com/track/muhammad-ali">http://proleter.bandcamp.com/track/muhammad-ali</BANDCAMP></r>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('bandcamp');
				}
			],
			[
				'http://therunons.bandcamp.com/track/still-feel',
				'<r><BANDCAMP track_id="2146686782" url="http://therunons.bandcamp.com/track/still-feel">http://therunons.bandcamp.com/track/still-feel</BANDCAMP></r>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('bandcamp');
				}
			],
			[
				'http://blip.tv/hilah-cooking/hilah-cooking-vegetable-beef-stew-6663725',
				'<r><BLIP id="AYOW3REC" url="http://blip.tv/hilah-cooking/hilah-cooking-vegetable-beef-stew-6663725">http://blip.tv/hilah-cooking/hilah-cooking-vegetable-beef-stew-6663725</BLIP></r>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('blip');
				}
			],
			[
				'http://blip.tv/blip-on-blip/damian-bruno-and-vinyl-rewind-blip-on-blip-58-5226104',
				'<r><BLIP id="zEiCvv1cAg" url="http://blip.tv/blip-on-blip/damian-bruno-and-vinyl-rewind-blip-on-blip-58-5226104">http://blip.tv/blip-on-blip/damian-bruno-and-vinyl-rewind-blip-on-blip-58-5226104</BLIP></r>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('blip');
				}
			],
			[
				'http://www.colbertnation.com/the-colbert-report-videos/429637/october-14-2013/5-x-five---colbert-moments--under-the-desk',
				'<r><COLBERTNATION id="mgid:cms:video:colbertnation.com:429637" url="http://www.colbertnation.com/the-colbert-report-videos/429637/october-14-2013/5-x-five---colbert-moments--under-the-desk">http://www.colbertnation.com/the-colbert-report-videos/429637/october-14-2013/5-x-five---colbert-moments--under-the-desk</COLBERTNATION></r>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('colbertnation');
				}
			],
			[
				'http://www.colbertnation.com/the-colbert-report-collections/429799/sorry--technical-difficulties/',
				'<r><COLBERTNATION id="mgid:cms:video:colbertnation.com:427533" url="http://www.colbertnation.com/the-colbert-report-collections/429799/sorry--technical-difficulties/">http://www.colbertnation.com/the-colbert-report-collections/429799/sorry--technical-difficulties/</COLBERTNATION></r>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('colbertnation');
				}
			],
			[
				'http://www.comedycentral.com/video-clips/uu5qz4/key-and-peele-dueling-hats',
				'<r><COMEDYCENTRAL id="mgid:arc:video:comedycentral.com:bc275e2f-48e3-46d9-b095-0254381497ea" url="http://www.comedycentral.com/video-clips/uu5qz4/key-and-peele-dueling-hats">http://www.comedycentral.com/video-clips/uu5qz4/key-and-peele-dueling-hats</COMEDYCENTRAL></r>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('comedycentral');
				}
			],
			[
				'http://www.thedailyshow.com/collection/429537/shutstorm-2013/429508',
				'<r><DAILYSHOW id="mgid:cms:video:thedailyshow.com:429537" url="http://www.thedailyshow.com/collection/429537/shutstorm-2013/429508">http://www.thedailyshow.com/collection/429537/shutstorm-2013/429508</DAILYSHOW></r>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('dailyshow');
				}
			],
			[
				'http://www.thedailyshow.com/watch/mon-july-16-2012/louis-c-k-',
				'<r><DAILYSHOW id="mgid:cms:video:thedailyshow.com:416478" url="http://www.thedailyshow.com/watch/mon-july-16-2012/louis-c-k-">http://www.thedailyshow.com/watch/mon-july-16-2012/louis-c-k-</DAILYSHOW></r>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('dailyshow');
				}
			],
			[
				'http://www.gametrailers.com/videos/jz8rt1/tom-clancy-s-the-division-vgx-2013--world-premiere-featurette-',
				'<r><GAMETRAILERS id="mgid:arc:video:gametrailers.com:85dee3c3-60f6-4b80-8124-cf3ebd9d2a6c" url="http://www.gametrailers.com/videos/jz8rt1/tom-clancy-s-the-division-vgx-2013--world-premiere-featurette-">http://www.gametrailers.com/videos/jz8rt1/tom-clancy-s-the-division-vgx-2013--world-premiere-featurette-</GAMETRAILERS></r>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('gametrailers');
				}
			],
			[
				'http://www.gametrailers.com/reviews/zalxz0/crimson-dragon-review',
				'<r><GAMETRAILERS id="mgid:arc:video:gametrailers.com:31c93ab8-fe77-4db2-bfee-ff37837e6704" url="http://www.gametrailers.com/reviews/zalxz0/crimson-dragon-review">http://www.gametrailers.com/reviews/zalxz0/crimson-dragon-review</GAMETRAILERS></r>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('gametrailers');
				}
			],
			[
				'http://www.gametrailers.com/full-episodes/zdzfok/pop-fiction-episode-40--jak-ii--sandover-village',
				'<r><GAMETRAILERS id="mgid:arc:episode:gametrailers.com:1e287a4e-b795-4c7f-9d48-1926eafb5740" url="http://www.gametrailers.com/full-episodes/zdzfok/pop-fiction-episode-40--jak-ii--sandover-village">http://www.gametrailers.com/full-episodes/zdzfok/pop-fiction-episode-40--jak-ii--sandover-village</GAMETRAILERS></r>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('gametrailers');
				}
			],
			[
				'http://gfycat.com/SereneIllfatedCapybara',
				'<r><GFYCAT height="338" id="SereneIllfatedCapybara" url="http://gfycat.com/SereneIllfatedCapybara" width="600">http://gfycat.com/SereneIllfatedCapybara</GFYCAT></r>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('gfycat');
				}
			],
			[
				'http://grooveshark.com/s/Soul+Below/4zGL7i?src=5',
				'<r><GROOVESHARK songid="35292216" url="http://grooveshark.com/s/Soul+Below/4zGL7i?src=5">http://grooveshark.com/s/Soul+Below/4zGL7i?src=5</GROOVESHARK></r>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('grooveshark');
				}
			],
			[
				'http://grooveshark.com/#!/s/Soul+Below/4zGL7i?src=5',
				'<r><GROOVESHARK songid="35292216" url="http://grooveshark.com/#!/s/Soul+Below/4zGL7i?src=5">http://grooveshark.com/#!/s/Soul+Below/4zGL7i?src=5</GROOVESHARK></r>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('grooveshark');
				}
			],
			[
				'http://www.hulu.com/watch/484180',
				'<r><HULU id="zPFCgxncn97IFkqEnZ-kRA" url="http://www.hulu.com/watch/484180">http://www.hulu.com/watch/484180</HULU></r>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('hulu');
				}
			],
			[
				'http://www.indiegogo.com/projects/gameheart-redesigned',
				'<r><INDIEGOGO id="513633" url="http://www.indiegogo.com/projects/gameheart-redesigned">http://www.indiegogo.com/projects/gameheart-redesigned</INDIEGOGO></r>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('indiegogo');
				}
			],
			[
				'http://www.indiegogo.com/projects/5050-years-a-documentary',
				'<r><INDIEGOGO id="535215" url="http://www.indiegogo.com/projects/5050-years-a-documentary">http://www.indiegogo.com/projects/5050-years-a-documentary</INDIEGOGO></r>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('indiegogo');
				}
			],
			[
				'http://rutube.ru/video/b920dc58f1397f1761a226baae4d2f3b/',
				'<r><RUTUBE id="6613980" url="http://rutube.ru/video/b920dc58f1397f1761a226baae4d2f3b/">http://rutube.ru/video/b920dc58f1397f1761a226baae4d2f3b/</RUTUBE></r>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('rutube');
				}
			],
			[
				'http://www.slideshare.net/Slideshare/10-million-uploads-our-favorites',
				'<r><SLIDESHARE id="21112125" url="http://www.slideshare.net/Slideshare/10-million-uploads-our-favorites">http://www.slideshare.net/Slideshare/10-million-uploads-our-favorites</SLIDESHARE></r>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('slideshare');
				}
			],
			[
				'https://soundcloud.com/matt0753/iroh-ii-deep-voice/s-UpqTm',
				'<r><SOUNDCLOUD id="https://soundcloud.com/matt0753/iroh-ii-deep-voice/s-UpqTm" secret_token="s-UpqTm" track_id="51465673" url="https://soundcloud.com/matt0753/iroh-ii-deep-voice/s-UpqTm">https://soundcloud.com/matt0753/iroh-ii-deep-voice/s-UpqTm</SOUNDCLOUD></r>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('soundcloud');
				}
			],
			[
				'https://soundcloud.com/swami-john/sets/auto-midnight-scrap-heap/s-0WDep',
				'<r><SOUNDCLOUD id="https://soundcloud.com/swami-john/sets/auto-midnight-scrap-heap/s-0WDep" playlist_id="3111458" secret_token="s-0WDep" url="https://soundcloud.com/swami-john/sets/auto-midnight-scrap-heap/s-0WDep">https://soundcloud.com/swami-john/sets/auto-midnight-scrap-heap/s-0WDep</SOUNDCLOUD></r>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('soundcloud');
				}
			],
			[
				'http://teamcoco.com/video/serious-jibber-jabber-a-scott-berg-full-episode',
				'<r><TEAMCOCO id="73784" url="http://teamcoco.com/video/serious-jibber-jabber-a-scott-berg-full-episode">http://teamcoco.com/video/serious-jibber-jabber-a-scott-berg-full-episode</TEAMCOCO></r>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('teamcoco');
				}
			],
			[
				'http://www.traileraddict.com/trailer/watchmen/feature-trailer',
				'<r><TRAILERADDICT id="7376" url="http://www.traileraddict.com/trailer/watchmen/feature-trailer">http://www.traileraddict.com/trailer/watchmen/feature-trailer</TRAILERADDICT></r>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('traileraddict');
				}
			],
			[
				'http://www.twitch.tv/m/57217',
				'<r><TWITCH archive_id="435873548" channel="wcs_america" url="http://www.twitch.tv/m/57217">http://www.twitch.tv/m/57217</TWITCH></r>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('twitch');
				}
			],
			[
				'http://www.ustream.tv/channel/ps4-ustream-gameplay',
				'<r><USTREAM cid="16234409" url="http://www.ustream.tv/channel/ps4-ustream-gameplay">http://www.ustream.tv/channel/ps4-ustream-gameplay</USTREAM></r>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('ustream');
				}
			],
			[
				'http://vk.com/video-7016284_163645555',
				'<r><VK hash="eb5d7a5e6e1d8b71" oid="-7016284" url="http://vk.com/video-7016284_163645555" vid="163645555">http://vk.com/video-7016284_163645555</VK></r>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('vk');
				}
			],
		];
	}

	/**
	* @testdox Scraping+rendering tests
	* @dataProvider getScrapingRenderingTests
	* @group needs-network
	*/
	public function testScrapingRendering()
	{
		call_user_func_array([$this, 'testRendering'], func_get_args());
	}

	public function getScrapingRenderingTests()
	{
		return [
			[
				'http://proleter.bandcamp.com/album/curses-from-past-times-ep',
				'<iframe width="400" height="120" allowfullscreen="" frameborder="0" scrolling="no" src="//bandcamp.com/EmbeddedPlayer/album=1122163921/size=medium"></iframe>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('bandcamp');
				}
			],
			[
				'http://proleter.bandcamp.com/track/muhammad-ali',
				'<iframe width="400" height="42" allowfullscreen="" frameborder="0" scrolling="no" src="//bandcamp.com/EmbeddedPlayer/album=1122163921/size=small/t=7"></iframe>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('bandcamp');
				}
			],
			[
				'http://therunons.bandcamp.com/track/still-feel',
				'<iframe width="400" height="42" allowfullscreen="" frameborder="0" scrolling="no" src="//bandcamp.com/EmbeddedPlayer/track=2146686782/size=small"></iframe>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('bandcamp');
				}
			],
			[
				'http://www.colbertnation.com/the-colbert-report-videos/429637/october-14-2013/5-x-five---colbert-moments--under-the-desk',
				'<iframe width="512" height="288" src="http://media.mtvnservices.com/embed/mgid:cms:video:colbertnation.com:429637" allowfullscreen="" frameborder="0" scrolling="no"></iframe>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('colbertnation');
				}
			],
			[
				'http://www.comedycentral.com/video-clips/uu5qz4/key-and-peele-dueling-hats',
				'<iframe width="512" height="288" src="http://media.mtvnservices.com/embed/mgid:arc:video:comedycentral.com:bc275e2f-48e3-46d9-b095-0254381497ea" allowfullscreen="" frameborder="0" scrolling="no"></iframe>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('comedycentral');
				}
			],
			[
				'http://www.thedailyshow.com/collection/429537/shutstorm-2013/429508',
				'<iframe width="512" height="288" src="http://media.mtvnservices.com/embed/mgid:cms:video:thedailyshow.com:429537" allowfullscreen="" frameborder="0" scrolling="no"></iframe>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('dailyshow');
				}
			],
			[
				'http://gfycat.com/SereneIllfatedCapybara',
				'<iframe width="600" height="338" src="http://gfycat.com/iframe/SereneIllfatedCapybara" allowfullscreen="" frameborder="0" scrolling="no"></iframe>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('gfycat');
				}
			],
			[
				'http://grooveshark.com/s/Soul+Below/4zGL7i?src=5',
				'<object type="application/x-shockwave-flash" typemustmatch="" width="250" height="40" data="//grooveshark.com/songWidget.swf"><param name="allowfullscreen" value="true"><param name="flashvars" value="playlistID=&amp;songID=35292216"><embed type="application/x-shockwave-flash" src="//grooveshark.com/songWidget.swf" width="250" height="40" allowfullscreen="" flashvars="playlistID=&amp;songID=35292216"></object>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('grooveshark');
				}
			],
			[
				'https://soundcloud.com/matt0753/iroh-ii-deep-voice/s-UpqTm',
				'<iframe width="560" height="166" allowfullscreen="" frameborder="0" scrolling="no" src="https://w.soundcloud.com/player/?url=https://api.soundcloud.com/tracks/51465673&amp;secret_token=s-UpqTm"></iframe>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('soundcloud');
				}
			],
			[
				'http://www.ustream.tv/channel/ps4-ustream-gameplay',
				'<iframe width="480" height="302" allowfullscreen="" frameborder="0" scrolling="no" src="http://www.ustream.tv/embed/16234409"></iframe>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('ustream');
				}
			],
		];
	}

	public function getParsingTests()
	{
		return [
			// =================================================================
			// Abstract tests
			// =================================================================
			[
				// Ensure that non-HTTP URLs don't get scraped
				'[media]invalid://example.org/123[/media]',
				'<t>[media]invalid://example.org/123[/media]</t>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add(
						'example',
						[
							'host'   => 'example.org',
							'scrape' => [
								'match'   => '/./',
								'extract' => "/(?'id'[0-9]+)/"
							],
							'iframe' => ['width' => 1, 'height' => 1, 'src' => '{@id}']
						]
					);
				}
			],
			[
				// Ensure that invalid URLs don't get scraped
				'[media]http://example.invalid/123?x"> foo="bar[/media]',
				'<t>[media]http://example.invalid/123?x"&gt; foo="bar[/media]</t>',
				['captureURLs' => false],
				function ($configurator)
				{
					$configurator->MediaEmbed->add(
						'example',
						[
							'host'   => 'example.invalid',
							'scrape' => [
								'match'   => '/./',
								'extract' => "/(?'id'[0-9]+)/"
							],
							'iframe' => ['width' => 1, 'height' => 1, 'src' => '{@id}']
						]
					);
				}
			],
			[
				// Ensure that we don't scrape the URL if it doesn't match
				'[media]http://example.invalid/123[/media]',
				'<t>[media]http://example.invalid/123[/media]</t>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add(
						'example',
						[
							'host'   => 'example.invalid',
							'scrape' => [
								'match'   => '/XXX/',
								'extract' => "/(?'id'[0-9]+)/"
							],
							'iframe' => ['width' => 1, 'height' => 1, 'src' => '{@id}']
						]
					);
				}
			],
			[
				// Ensure that we don't scrape if the attributes are already filled
				'http://example.invalid/123',
				'<r><EXAMPLE id="12" url="http://example.invalid/123">http://example.invalid/123</EXAMPLE></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add(
						'example',
						[
							'host'    => 'example.invalid',
							'extract' => "#/(?'id'[0-9]{2})#",
							'scrape'  => [
								'match'   => '/./',
								'extract' => "/(?'id'[0-9]+)/"
							],
							'iframe'  => ['width' => 1, 'height' => 1, 'src' => '{@id}']
						]
					);
				}
			],
			[
				'[media]http://foo.example.org/123[/media]',
				'<r><X2 id="123" url="http://foo.example.org/123">[media]http://foo.example.org/123[/media]</X2></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add(
						'x1',
						[
							'host'     => 'example.org',
							'extract'  => "/(?'id'\\d+)/",
							'template' => ''
						]
					);
					$configurator->MediaEmbed->add(
						'x2',
						[
							'host'     => 'foo.example.org',
							'extract'  => "/(?'id'\\d+)/",
							'template' => ''
						]
					);
				}
			],
			[
				// Ensure no bad things(tm) happen when there's no match
				'[media]http://example.org/123[/media]',
				'<t>[media]http://example.org/123[/media]</t>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add(
						'x2',
						[
							'host'     => 'foo.example.org',
							'extract'  => "/(?'id'\\d+)/",
							'template' => ''
						]
					);
				}
			],
			[
				// Test that we don't replace the "id" attribute with an URL
				'[media=foo]http://example.org/123[/media]',
				'<r><FOO id="123" url="http://example.org/123">[media=foo]http://example.org/123[/media]</FOO></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add(
						'foo',
						[
							'host'     => 'foo.example.org',
							'extract'  => "/(?'id'\\d+)/",
							'template' => ''
						]
					);
				}
			],
			[
				'[media]http://example.com/baz[/media]',
				'<t>[media]http://example.com/baz[/media]</t>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add(
						'foo',
						[
							'host'     => 'example.com',
							'extract'  => [
								"!example\\.com/(?<foo>foo)!",
								"!example\\.com/(?<bar>bar)!"
							],
							'template' => 'foo'
						]
					);
				}
			],
			[
				'[media]http://example.com/foo[/media]',
				'<r><FOO foo="foo" url="http://example.com/foo">[media]http://example.com/foo[/media]</FOO></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add(
						'foo',
						[
							'host'     => 'example.com',
							'extract'  => [
								"!example\\.com/(?<foo>foo)!",
								"!example\\.com/(?<bar>bar)!"
							],
							'template' => 'foo'
						]
					);
				}
			],
			[
				// @bar is invalid, no match == tag is invalidated
				'[foo bar=BAR]http://example.com/baz[/foo]',
				'<t>[foo bar=BAR]http://example.com/baz[/foo]</t>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add(
						'foo',
						[
							'host'     => 'example.com',
							'extract'  => [
								"!example\\.com/(?<foo>foo)!",
								"!example\\.com/(?<bar>bar)!"
							],
							'template' => 'foo'
						]
					);
				}
			],
			[
				// No match on URL but @bar is valid == tag is kept
				'[foo bar=bar]http://example.com/baz[/foo]',
				'<r><FOO bar="bar" url="http://example.com/baz"><s>[foo bar=bar]</s>http://example.com/baz<e>[/foo]</e></FOO></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add(
						'foo',
						[
							'host'     => 'example.com',
							'extract'  => [
								"!example\\.com/(?<foo>foo)!",
								"!example\\.com/(?<bar>bar)!"
							],
							'template' => 'foo'
						]
					);
				}
			],
			// =================================================================
			// Bundled sites tests
			// =================================================================
			[
				'http://blip.tv/play/AYKn_x0A',
				'<r><BLIP id="AYKn_x0A" url="http://blip.tv/play/AYKn_x0A">http://blip.tv/play/AYKn_x0A</BLIP></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('blip');
				}
			],
			[
				'http://blip.tv/play/AYGJ%2BSkC',
				'<r><BLIP id="AYGJ%2BSkC" url="http://blip.tv/play/AYGJ%2BSkC">http://blip.tv/play/AYGJ%2BSkC</BLIP></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('blip');
				}
			],
			[
				'http://blip.tv/play/AYGJ+SkC',
				'<r><BLIP id="AYGJ+SkC" url="http://blip.tv/play/AYGJ+SkC">http://blip.tv/play/AYGJ+SkC</BLIP></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('blip');
				}
			],
			[
				'http://www.break.com/video/video-game-playing-frog-wants-more-2278131',
				'<r><BREAK id="2278131" url="http://www.break.com/video/video-game-playing-frog-wants-more-2278131">http://www.break.com/video/video-game-playing-frog-wants-more-2278131</BREAK></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('break');
				}
			],
			[
				'http://www.cbsnews.com/video/watch/?id=50156501n',
				'<r><CBSNEWS id="50156501" url="http://www.cbsnews.com/video/watch/?id=50156501n">http://www.cbsnews.com/video/watch/?id=50156501n</CBSNEWS></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('cbsnews');
				}
			],
			[
				'http://edition.cnn.com/video/data/2.0/video/showbiz/2013/10/25/spc-preview-savages-stephen-king-thor.cnn.html',
				'<r><CNN id="showbiz/2013/10/25/spc-preview-savages-stephen-king-thor.cnn" url="http://edition.cnn.com/video/data/2.0/video/showbiz/2013/10/25/spc-preview-savages-stephen-king-thor.cnn.html">http://edition.cnn.com/video/data/2.0/video/showbiz/2013/10/25/spc-preview-savages-stephen-king-thor.cnn.html</CNN></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('cnn');
				}
			],
			[
				'http://us.cnn.com/video/data/2.0/video/bestoftv/2013/10/23/vo-nr-prince-george-christening-arrival.cnn.html',
				'<r><CNN id="bestoftv/2013/10/23/vo-nr-prince-george-christening-arrival.cnn" url="http://us.cnn.com/video/data/2.0/video/bestoftv/2013/10/23/vo-nr-prince-george-christening-arrival.cnn.html">http://us.cnn.com/video/data/2.0/video/bestoftv/2013/10/23/vo-nr-prince-george-christening-arrival.cnn.html</CNN></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('cnn');
				}
			],
			[
				'http://www.collegehumor.com/video/1181601/more-than-friends',
				'<r><COLLEGEHUMOR id="1181601" url="http://www.collegehumor.com/video/1181601/more-than-friends">http://www.collegehumor.com/video/1181601/more-than-friends</COLLEGEHUMOR></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('collegehumor');
				}
			],
			[
				'http://www.dailymotion.com/video/x222z1',
				'<r><DAILYMOTION id="x222z1" url="http://www.dailymotion.com/video/x222z1">http://www.dailymotion.com/video/x222z1</DAILYMOTION></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('dailymotion');
				}
			],
			[
				'http://www.dailymotion.com/user/Dailymotion/2#video=x222z1',
				'<r><DAILYMOTION id="x222z1" url="http://www.dailymotion.com/user/Dailymotion/2#video=x222z1">http://www.dailymotion.com/user/Dailymotion/2#video=x222z1</DAILYMOTION></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('dailymotion');
				}
			],
			[
				'http://espn.go.com/video/clip?id=espn:9895232',
				'<r><ESPN id="espn:9895232" url="http://espn.go.com/video/clip?id=espn:9895232">http://espn.go.com/video/clip?id=espn:9895232</ESPN></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('espn');
				}
			],
			[
				'https://www.facebook.com/photo.php?v=10100658170103643&set=vb.20531316728&type=3&theater',
				'<r><FACEBOOK id="10100658170103643" url="https://www.facebook.com/photo.php?v=10100658170103643&amp;set=vb.20531316728&amp;type=3&amp;theater">https://www.facebook.com/photo.php?v=10100658170103643&amp;set=vb.20531316728&amp;type=3&amp;theater</FACEBOOK></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('facebook');
				}
			],
			[
				'https://www.facebook.com/video/video.php?v=10150451523596807',
				'<r><FACEBOOK id="10150451523596807" url="https://www.facebook.com/video/video.php?v=10150451523596807">https://www.facebook.com/video/video.php?v=10150451523596807</FACEBOOK></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('facebook');
				}
			],
			[
				'http://www.funnyordie.com/videos/bf313bd8b4/murdock-with-keith-david',
				'<r><FUNNYORDIE id="bf313bd8b4" url="http://www.funnyordie.com/videos/bf313bd8b4/murdock-with-keith-david">http://www.funnyordie.com/videos/bf313bd8b4/murdock-with-keith-david</FUNNYORDIE></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('funnyordie');
				}
			],
			[
				'http://www.gamespot.com/destiny/videos/destiny-the-moon-trailer-6415176/',
				'<r><GAMESPOT id="6415176" url="http://www.gamespot.com/destiny/videos/destiny-the-moon-trailer-6415176/">http://www.gamespot.com/destiny/videos/destiny-the-moon-trailer-6415176/</GAMESPOT></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('gamespot');
				}
			],
			[
				'http://www.gamespot.com/events/game-crib-tsm-snapdragon/gamecrib-extras-cooking-with-dan-dinh-6412922/',
				'<r><GAMESPOT id="6412922" url="http://www.gamespot.com/events/game-crib-tsm-snapdragon/gamecrib-extras-cooking-with-dan-dinh-6412922/">http://www.gamespot.com/events/game-crib-tsm-snapdragon/gamecrib-extras-cooking-with-dan-dinh-6412922/</GAMESPOT></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('gamespot');
				}
			],
			[
				'http://www.gamespot.com/videos/beat-the-pros-pax-prime-2013/2300-6414307/',
				'<r><GAMESPOT id="6414307" url="http://www.gamespot.com/videos/beat-the-pros-pax-prime-2013/2300-6414307/">http://www.gamespot.com/videos/beat-the-pros-pax-prime-2013/2300-6414307/</GAMESPOT></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('gamespot');
				}
			],
			[
				'https://gist.github.com/s9e/6806305',
				'<r><GIST id="s9e/6806305" url="https://gist.github.com/s9e/6806305">https://gist.github.com/s9e/6806305</GIST></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('gist');
				}
			],
			[
				'https://gist.github.com/6806305',
				'<r><GIST id="6806305" url="https://gist.github.com/6806305">https://gist.github.com/6806305</GIST></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('gist');
				}
			],
			[
				'https://gist.github.com/s9e/6806305/ad88d904b082c8211afa040162402015aacb8599',
				'<r><GIST id="s9e/6806305/ad88d904b082c8211afa040162402015aacb8599" url="https://gist.github.com/s9e/6806305/ad88d904b082c8211afa040162402015aacb8599">https://gist.github.com/s9e/6806305/ad88d904b082c8211afa040162402015aacb8599</GIST></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('gist');
				}
			],
			[
				'http://grooveshark.com/playlist/Purity+Ring+Shrines/74854761',
				'<r><GROOVESHARK playlistid="74854761" url="http://grooveshark.com/playlist/Purity+Ring+Shrines/74854761">http://grooveshark.com/playlist/Purity+Ring+Shrines/74854761</GROOVESHARK></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('grooveshark');
				}
			],
			[
				'http://grooveshark.com/#!/playlist/Purity+Ring+Shrines/74854761',
				'<r><GROOVESHARK playlistid="74854761" url="http://grooveshark.com/#!/playlist/Purity+Ring+Shrines/74854761">http://grooveshark.com/#!/playlist/Purity+Ring+Shrines/74854761</GROOVESHARK></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('grooveshark');
				}
			],
			[
				'http://uk.ign.com/videos/2013/07/12/pokemon-x-version-pokemon-y-version-battle-trailer',
				'<r><IGN id="http://uk.ign.com/videos/2013/07/12/pokemon-x-version-pokemon-y-version-battle-trailer" url="http://uk.ign.com/videos/2013/07/12/pokemon-x-version-pokemon-y-version-battle-trailer">http://uk.ign.com/videos/2013/07/12/pokemon-x-version-pokemon-y-version-battle-trailer</IGN></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('ign');
				}
			],
			[
				'http://www.indiegogo.com/projects/513633',
				'<r><INDIEGOGO id="513633" url="http://www.indiegogo.com/projects/513633">http://www.indiegogo.com/projects/513633</INDIEGOGO></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('indiegogo');
				}
			],
			[
				'http://instagram.com/p/gbGaIXBQbn/',
				'<r><INSTAGRAM id="gbGaIXBQbn" url="http://instagram.com/p/gbGaIXBQbn/">http://instagram.com/p/gbGaIXBQbn/</INSTAGRAM></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('instagram');
				}
			],
			[
				'http://www.kickstarter.com/projects/1869987317/wish-i-was-here-1?ref=',
				'<r><KICKSTARTER id="1869987317/wish-i-was-here-1" url="http://www.kickstarter.com/projects/1869987317/wish-i-was-here-1?ref=">http://www.kickstarter.com/projects/1869987317/wish-i-was-here-1?ref=</KICKSTARTER></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('kickstarter');
				}
			],
			[
				'http://www.kickstarter.com/projects/1869987317/wish-i-was-here-1/widget/card.html',
				'<r><KICKSTARTER card="card" id="1869987317/wish-i-was-here-1" url="http://www.kickstarter.com/projects/1869987317/wish-i-was-here-1/widget/card.html">http://www.kickstarter.com/projects/1869987317/wish-i-was-here-1/widget/card.html</KICKSTARTER></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('kickstarter');
				}
			],
			[
				'http://www.kickstarter.com/projects/1869987317/wish-i-was-here-1/widget/video.html',
				'<r><KICKSTARTER id="1869987317/wish-i-was-here-1" url="http://www.kickstarter.com/projects/1869987317/wish-i-was-here-1/widget/video.html" video="video">http://www.kickstarter.com/projects/1869987317/wish-i-was-here-1/widget/video.html</KICKSTARTER></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('kickstarter');
				}
			],
			[
				'http://www.liveleak.com/view?i=3dd_1366238099',
				'<r><LIVELEAK id="3dd_1366238099" url="http://www.liveleak.com/view?i=3dd_1366238099">http://www.liveleak.com/view?i=3dd_1366238099</LIVELEAK></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('liveleak');
				}
			],
			[
				'http://www.metacafe.com/watch/10785282/chocolate_treasure_chest_epic_meal_time/',
				'<r><METACAFE id="10785282" url="http://www.metacafe.com/watch/10785282/chocolate_treasure_chest_epic_meal_time/">http://www.metacafe.com/watch/10785282/chocolate_treasure_chest_epic_meal_time/</METACAFE></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('metacafe');
				}
			],
			[
				'http://rutube.ru/tracks/4118278.html?v=8b490a46447720d4ad74616f5de2affd',
				'<r><RUTUBE id="4118278" url="http://rutube.ru/tracks/4118278.html?v=8b490a46447720d4ad74616f5de2affd">http://rutube.ru/tracks/4118278.html?v=8b490a46447720d4ad74616f5de2affd</RUTUBE></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('rutube');
				}
			],
			[
				'http://www.slideshare.net/Slideshare/how-23431564',
				'<r><SLIDESHARE id="23431564" url="http://www.slideshare.net/Slideshare/how-23431564">http://www.slideshare.net/Slideshare/how-23431564</SLIDESHARE></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('slideshare');
				}
			],
			[
				// Taken from the "WordPress Code" button of the page
				'[soundcloud url="http://api.soundcloud.com/tracks/98282116" params="" width=" 100%" height="166" iframe="true" /]',
				'<r><SOUNDCLOUD id="http://api.soundcloud.com/tracks/98282116" url="http://api.soundcloud.com/tracks/98282116">[soundcloud url="http://api.soundcloud.com/tracks/98282116" params="" width=" 100%" height="166" iframe="true" /]</SOUNDCLOUD></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('soundcloud');
				}
			],
			[
				'[soundcloud url="https://api.soundcloud.com/tracks/12345?secret_token=s-foobar" width="100%" height="166" iframe="true" /]',
				'<r><SOUNDCLOUD id="https://api.soundcloud.com/tracks/12345?secret_token=s-foobar" secret_token="s-foobar" url="https://api.soundcloud.com/tracks/12345?secret_token=s-foobar">[soundcloud url="https://api.soundcloud.com/tracks/12345?secret_token=s-foobar" width="100%" height="166" iframe="true" /]</SOUNDCLOUD></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('soundcloud');
				}
			],
			[
				'https://soundcloud.com/andrewbird/three-white-horses',
				'<r><SOUNDCLOUD id="https://soundcloud.com/andrewbird/three-white-horses" url="https://soundcloud.com/andrewbird/three-white-horses">https://soundcloud.com/andrewbird/three-white-horses</SOUNDCLOUD></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('soundcloud');
				}
			],
			[
				'https://soundcloud.com/tenaciousd/sets/rize-of-the-fenix/',
				'<r><SOUNDCLOUD id="https://soundcloud.com/tenaciousd/sets/rize-of-the-fenix" url="https://soundcloud.com/tenaciousd/sets/rize-of-the-fenix/">https://soundcloud.com/tenaciousd/sets/rize-of-the-fenix/</SOUNDCLOUD></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('soundcloud');
				}
			],
			[
				'[soundcloud url="https://api.soundcloud.com/playlists/1919974" width="100%" height="450" iframe="true" /]',
				'<r><SOUNDCLOUD id="https://api.soundcloud.com/playlists/1919974" url="https://api.soundcloud.com/playlists/1919974">[soundcloud url="https://api.soundcloud.com/playlists/1919974" width="100%" height="450" iframe="true" /]</SOUNDCLOUD></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('soundcloud');
				}
			],
			[
				'spotify:track:5JunxkcjfCYcY7xJ29tLai',
				'<r><SPOTIFY uri="spotify:track:5JunxkcjfCYcY7xJ29tLai">spotify:track:5JunxkcjfCYcY7xJ29tLai</SPOTIFY></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('spotify');
				}
			],
			[
				'[spotify]spotify:trackset:PREFEREDTITLE:5Z7ygHQo02SUrFmcgpwsKW,1x6ACsKV4UdWS2FMuPFUiT,4bi73jCM02fMpkI11Lqmfe[/spotify]',
				'<r><SPOTIFY uri="spotify:trackset:PREFEREDTITLE:5Z7ygHQo02SUrFmcgpwsKW,1x6ACsKV4UdWS2FMuPFUiT,4bi73jCM02fMpkI11Lqmfe"><s>[spotify]</s>spotify:trackset:PREFEREDTITLE:5Z7ygHQo02SUrFmcgpwsKW,1x6ACsKV4UdWS2FMuPFUiT,4bi73jCM02fMpkI11Lqmfe<e>[/spotify]</e></SPOTIFY></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('spotify');
				}
			],
			[
				'http://open.spotify.com/user/ozmoetr/playlist/4yRrCWNhWOqWZx5lmFqZvt',
				'<r><SPOTIFY path="user/ozmoetr/playlist/4yRrCWNhWOqWZx5lmFqZvt" url="http://open.spotify.com/user/ozmoetr/playlist/4yRrCWNhWOqWZx5lmFqZvt">http://open.spotify.com/user/ozmoetr/playlist/4yRrCWNhWOqWZx5lmFqZvt</SPOTIFY></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('spotify');
				}
			],
			[
				'https://play.spotify.com/track/6acKqVtKngFXApjvXsU6mQ',
				'<r><SPOTIFY path="track/6acKqVtKngFXApjvXsU6mQ" url="https://play.spotify.com/track/6acKqVtKngFXApjvXsU6mQ">https://play.spotify.com/track/6acKqVtKngFXApjvXsU6mQ</SPOTIFY></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('spotify');
				}
			],
			[
				'http://strawpoll.me/738091',
				'<r><STRAWPOLL id="738091" url="http://strawpoll.me/738091">http://strawpoll.me/738091</STRAWPOLL></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('strawpoll');
				}
			],
			[
				'http://teamcoco.com/video/73784/historian-a-scott-berg-serious-jibber-jabber-with-conan-obrien',
				'<r><TEAMCOCO id="73784" url="http://teamcoco.com/video/73784/historian-a-scott-berg-serious-jibber-jabber-with-conan-obrien">http://teamcoco.com/video/73784/historian-a-scott-berg-serious-jibber-jabber-with-conan-obrien</TEAMCOCO></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('teamcoco');
				}
			],
			[
				'http://www.ted.com/talks/eli_pariser_beware_online_filter_bubbles.html',
				'<r><TED id="talks/eli_pariser_beware_online_filter_bubbles.html" url="http://www.ted.com/talks/eli_pariser_beware_online_filter_bubbles.html">http://www.ted.com/talks/eli_pariser_beware_online_filter_bubbles.html</TED></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('ted');
				}
			],
			[
				'http://www.twitch.tv/minigolf2000',
				'<r><TWITCH channel="minigolf2000" url="http://www.twitch.tv/minigolf2000">http://www.twitch.tv/minigolf2000</TWITCH></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('twitch');
				}
			],
			[
				'http://www.twitch.tv/minigolf2000/c/2475925',
				'<r><TWITCH channel="minigolf2000" chapter_id="2475925" url="http://www.twitch.tv/minigolf2000/c/2475925">http://www.twitch.tv/minigolf2000/c/2475925</TWITCH></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('twitch');
				}
			],
			[
				'http://www.twitch.tv/minigolf2000/b/419320018',
				'<r><TWITCH archive_id="419320018" channel="minigolf2000" url="http://www.twitch.tv/minigolf2000/b/419320018">http://www.twitch.tv/minigolf2000/b/419320018</TWITCH></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('twitch');
				}
			],
			[
				'http://vimeo.com/67207222',
				'<r><VIMEO id="67207222" url="http://vimeo.com/67207222">http://vimeo.com/67207222</VIMEO></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('vimeo');
				}
			],
			[
				'http://www.ustream.tv/recorded/40771396',
				'<r><USTREAM url="http://www.ustream.tv/recorded/40771396" vid="40771396">http://www.ustream.tv/recorded/40771396</USTREAM></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('ustream');
				}
			],
			[
				'http://www.ustream.tv/explore/education',
				'<t>http://www.ustream.tv/explore/education</t>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('ustream');
				}
			],
			[
				'http://www.ustream.tv/upcoming',
				'<t>http://www.ustream.tv/upcoming</t>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('ustream');
				}
			],
			[
				'http://vimeo.com/channels/staffpicks/67207222',
				'<r><VIMEO id="67207222" url="http://vimeo.com/channels/staffpicks/67207222">http://vimeo.com/channels/staffpicks/67207222</VIMEO></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('vimeo');
				}
			],
			[
				'https://vine.co/v/bYwPIluIipH',
				'<r><VINE id="bYwPIluIipH" url="https://vine.co/v/bYwPIluIipH">https://vine.co/v/bYwPIluIipH</VINE></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('vine');
				}
			],
			[
				'http://www.worldstarhiphop.com/videos/video.php?v=wshhZ8F22UtJ8sLHdja0',
				'<r><WSHH id="wshhZ8F22UtJ8sLHdja0" url="http://www.worldstarhiphop.com/videos/video.php?v=wshhZ8F22UtJ8sLHdja0">http://www.worldstarhiphop.com/videos/video.php?v=wshhZ8F22UtJ8sLHdja0</WSHH></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('wshh');
				}
			],
			[
				'http://m.worldstarhiphop.com/video.php?v=wshh2SXFFe7W14DqQx61',
				'<r><WSHH id="wshh2SXFFe7W14DqQx61" url="http://m.worldstarhiphop.com/video.php?v=wshh2SXFFe7W14DqQx61">http://m.worldstarhiphop.com/video.php?v=wshh2SXFFe7W14DqQx61</WSHH></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('wshh');
				}
			],
			[
				'[media=youtube]-cEzsCAzTak[/media]',
				'<r><YOUTUBE id="-cEzsCAzTak">[media=youtube]-cEzsCAzTak[/media]</YOUTUBE></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('youtube');
				}
			],
			[
				'[media]http://www.youtube.com/watch?v=-cEzsCAzTak&feature=channel[/media]',
				'<r><YOUTUBE id="-cEzsCAzTak" url="http://www.youtube.com/watch?v=-cEzsCAzTak&amp;feature=channel">[media]http://www.youtube.com/watch?v=-cEzsCAzTak&amp;feature=channel[/media]</YOUTUBE></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('youtube');
				}
			],
			[
				'[YOUTUBE]-cEzsCAzTak[/YOUTUBE]',
				'<r><YOUTUBE id="-cEzsCAzTak"><s>[YOUTUBE]</s>-cEzsCAzTak<e>[/YOUTUBE]</e></YOUTUBE></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('youtube');
				}
			],
			[
				'[YOUTUBE]http://www.youtube.com/watch?v=-cEzsCAzTak&feature=channel[/YOUTUBE]',
				'<r><YOUTUBE id="-cEzsCAzTak" url="http://www.youtube.com/watch?v=-cEzsCAzTak&amp;feature=channel"><s>[YOUTUBE]</s>http://www.youtube.com/watch?v=-cEzsCAzTak&amp;feature=channel<e>[/YOUTUBE]</e></YOUTUBE></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('youtube');
				}
			],
			[
				'[YOUTUBE]http://www.youtube.com/watch?feature=player_embedded&v=-cEzsCAzTak[/YOUTUBE]',
				'<r><YOUTUBE id="-cEzsCAzTak" url="http://www.youtube.com/watch?feature=player_embedded&amp;v=-cEzsCAzTak"><s>[YOUTUBE]</s>http://www.youtube.com/watch?feature=player_embedded&amp;v=-cEzsCAzTak<e>[/YOUTUBE]</e></YOUTUBE></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('youtube');
				}
			],
			[
				'[YOUTUBE]http://www.youtube.com/v/-cEzsCAzTak[/YOUTUBE]',
				'<r><YOUTUBE id="-cEzsCAzTak" url="http://www.youtube.com/v/-cEzsCAzTak"><s>[YOUTUBE]</s>http://www.youtube.com/v/-cEzsCAzTak<e>[/YOUTUBE]</e></YOUTUBE></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('youtube');
				}
			],
			[
				'[YOUTUBE]http://youtu.be/-cEzsCAzTak[/YOUTUBE]',
				'<r><YOUTUBE id="-cEzsCAzTak" url="http://youtu.be/-cEzsCAzTak"><s>[YOUTUBE]</s>http://youtu.be/-cEzsCAzTak<e>[/YOUTUBE]</e></YOUTUBE></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('youtube');
				}
			],
			[
				'Check this: http://www.youtube.com/watch?v=-cEzsCAzTak and that: http://example.com',
				'<r>Check this: <YOUTUBE id="-cEzsCAzTak" url="http://www.youtube.com/watch?v=-cEzsCAzTak">http://www.youtube.com/watch?v=-cEzsCAzTak</YOUTUBE> and that: <URL url="http://example.com">http://example.com</URL></r>',
				[],
				function ($configurator)
				{
					$configurator->Autolink;
					$configurator->MediaEmbed->add('youtube');
				}
			],
			[
				'http://www.youtube.com/watch?feature=player_detailpage&v=9bZkp7q19f0#t=113',
				'<r><YOUTUBE id="9bZkp7q19f0" t="113" url="http://www.youtube.com/watch?feature=player_detailpage&amp;v=9bZkp7q19f0#t=113">http://www.youtube.com/watch?feature=player_detailpage&amp;v=9bZkp7q19f0#t=113</YOUTUBE></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('youtube');
				}
			],
			[
				'http://www.youtube.com/watch?feature=player_detailpage&v=9bZkp7q19f0&t=113',
				'<r><YOUTUBE id="9bZkp7q19f0" t="113" url="http://www.youtube.com/watch?feature=player_detailpage&amp;v=9bZkp7q19f0&amp;t=113">http://www.youtube.com/watch?feature=player_detailpage&amp;v=9bZkp7q19f0&amp;t=113</YOUTUBE></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('youtube');
				}
			],
			[
				'http://www.youtube.com/watch?v=pC35x6iIPmo&list=PLOU2XLYxmsIIxJrlMIY5vYXAFcO5g83gA',
				'<r><YOUTUBE id="pC35x6iIPmo" list="PLOU2XLYxmsIIxJrlMIY5vYXAFcO5g83gA" url="http://www.youtube.com/watch?v=pC35x6iIPmo&amp;list=PLOU2XLYxmsIIxJrlMIY5vYXAFcO5g83gA">http://www.youtube.com/watch?v=pC35x6iIPmo&amp;list=PLOU2XLYxmsIIxJrlMIY5vYXAFcO5g83gA</YOUTUBE></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('youtube');
				}
			],
			[
				'http://www.youtube.com/watch?v=pC35x6iIPmo&list=PLOU2XLYxmsIIxJrlMIY5vYXAFcO5g83gA#t=123',
				'<r><YOUTUBE id="pC35x6iIPmo" list="PLOU2XLYxmsIIxJrlMIY5vYXAFcO5g83gA" t="123" url="http://www.youtube.com/watch?v=pC35x6iIPmo&amp;list=PLOU2XLYxmsIIxJrlMIY5vYXAFcO5g83gA#t=123">http://www.youtube.com/watch?v=pC35x6iIPmo&amp;list=PLOU2XLYxmsIIxJrlMIY5vYXAFcO5g83gA#t=123</YOUTUBE></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('youtube');
				}
			],
			[
				'http://www.youtube.com/watch_popup?v=qybUFnY7Y8w',
				'<r><YOUTUBE id="qybUFnY7Y8w" url="http://www.youtube.com/watch_popup?v=qybUFnY7Y8w">http://www.youtube.com/watch_popup?v=qybUFnY7Y8w</YOUTUBE></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('youtube');
				}
			],
		];
	}

	public function getRenderingTests()
	{
		return [
			[
				'http://www.break.com/video/video-game-playing-frog-wants-more-2278131',
				'<iframe width="464" height="290" src="http://www.break.com/embed/2278131" allowfullscreen="" frameborder="0" scrolling="no"></iframe>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('break');
				}
			],
			[
				'http://www.cbsnews.com/video/watch/?id=50156501n',
				'<object type="application/x-shockwave-flash" typemustmatch="" width="425" height="279" data="http://cnettv.cnet.com/av/video/cbsnews/atlantis2/cbsnews_player_embed.swf"><param name="allowfullscreen" value="true"><param name="flashvars" value="si=254&amp;contentValue=50156501&amp;shareUrl=http://www.cbsnews.com/video/watch/?id=50156501n"><embed type="application/x-shockwave-flash" src="http://cnettv.cnet.com/av/video/cbsnews/atlantis2/cbsnews_player_embed.swf" width="425" height="279" allowfullscreen="" flashvars="si=254&amp;contentValue=50156501&amp;shareUrl=http://www.cbsnews.com/video/watch/?id=50156501n"></object>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('cbsnews');
				}
			],
			[
				'http://www.collegehumor.com/video/1181601/more-than-friends',
				'<iframe width="600" height="369" src="http://www.collegehumor.com/e/1181601" allowfullscreen="" frameborder="0" scrolling="no"></iframe>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('collegehumor');
				}
			],
			[
				'http://www.dailymotion.com/video/x222z1',
				'<iframe width="560" height="315" src="//www.dailymotion.com/embed/video/x222z1" allowfullscreen="" frameborder="0" scrolling="no"></iframe>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('dailymotion');
				}
			],
			[
				'http://espn.go.com/video/clip?id=espn:9895232',
				'<script src="http://player.espn.com/player.js?playerBrandingId=4ef8000cbaf34c1687a7d9a26fe0e89e&amp;adSetCode=91cDU6NuXTGKz3OdjOxFdAgJVtQcKJnI&amp;pcode=1kNG061cgaoolOncv54OAO1ceO-I&amp;width=576&amp;height=324&amp;externalId=espn:9895232&amp;thruParam_espn-ui%5BautoPlay%5D=false&amp;thruParam_espn-ui%5BplayRelatedExternally%5D=true"></script>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('espn');
				}
			],
			[
				'https://www.facebook.com/photo.php?v=10100658170103643&set=vb.20531316728&type=3&theater',
				'<iframe width="560" height="315" src="https://www.facebook.com/video/embed?video_id=10100658170103643" allowfullscreen="" frameborder="0" scrolling="no"></iframe>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('facebook');
				}
			],
			[
				'http://www.funnyordie.com/videos/bf313bd8b4/murdock-with-keith-david',
				'<iframe width="640" height="360" src="http://www.funnyordie.com/embed/bf313bd8b4" allowfullscreen="" frameborder="0" scrolling="no"></iframe>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('funnyordie');
				}
			],
			[
				'http://www.gamespot.com/destiny/videos/destiny-the-moon-trailer-6415176/',
				'<iframe width="640" height="400" src="//www.gamespot.com/videos/embed/6415176/" allowfullscreen="" frameborder="0" scrolling="no"></iframe>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('gamespot');
				}
			],
			[
				'https://gist.github.com/s9e/6806305',
				'<script src="https://gist.github.com/s9e/6806305.js"></script>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('gist');
				}
			],
			[
				'http://grooveshark.com/playlist/Purity+Ring+Shrines/74854761',
				'<object type="application/x-shockwave-flash" typemustmatch="" width="250" height="250" data="//grooveshark.com/widget.swf"><param name="allowfullscreen" value="true"><param name="flashvars" value="playlistID=74854761&amp;songID="><embed type="application/x-shockwave-flash" src="//grooveshark.com/widget.swf" width="250" height="250" allowfullscreen="" flashvars="playlistID=74854761&amp;songID="></object>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('grooveshark');
				}
			],
			[
				'http://uk.ign.com/videos/2013/07/12/pokemon-x-version-pokemon-y-version-battle-trailer',
				'<iframe width="468" height="263" src="http://widgets.ign.com/video/embed/content.html?url=http://uk.ign.com/videos/2013/07/12/pokemon-x-version-pokemon-y-version-battle-trailer" allowfullscreen="" frameborder="0" scrolling="no"></iframe>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('ign');
				}
			],
			[
				'http://www.indiegogo.com/projects/513633',
				'<iframe width="224" height="486" src="//www.indiegogo.com/project/513633/widget" allowfullscreen="" frameborder="0" scrolling="no"></iframe>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('indiegogo');
				}
			],
			[
				'http://www.kickstarter.com/projects/1869987317/wish-i-was-here-1?ref=',
				'<iframe width="220" height="380" src="//www.kickstarter.com/projects/1869987317/wish-i-was-here-1/widget/card.html" allowfullscreen="" frameborder="0" scrolling="no"></iframe>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('kickstarter');
				}
			],
			[
				'http://www.kickstarter.com/projects/1869987317/wish-i-was-here-1/widget/video.html',
				'<iframe width="480" height="360" src="//www.kickstarter.com/projects/1869987317/wish-i-was-here-1/widget/video.html" allowfullscreen="" frameborder="0" scrolling="no"></iframe>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('kickstarter');
				}
			],
			[
				'http://www.liveleak.com/view?i=3dd_1366238099',
				'<iframe width="640" height="360" src="http://www.liveleak.com/ll_embed?i=3dd_1366238099" allowfullscreen="" frameborder="0" scrolling="no"></iframe>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('liveleak');
				}
			],
			[
				'http://www.metacafe.com/watch/10785282/chocolate_treasure_chest_epic_meal_time/',
				'<iframe width="560" height="315" src="http://www.metacafe.com/embed/10785282/" allowfullscreen="" frameborder="0" scrolling="no"></iframe>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('metacafe');
				}
			],
			[
				'http://rutube.ru/tracks/4118278.html?v=8b490a46447720d4ad74616f5de2affd',
				'<iframe width="720" height="405" src="//rutube.ru/video/embed/4118278" allowfullscreen="" frameborder="0" scrolling="no"></iframe>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('rutube');
				}
			],
			[
				'http://www.slideshare.net/Slideshare/how-23431564',
				'<iframe width="427" height="356" src="//www.slideshare.net/slideshow/embed_code/23431564" allowfullscreen="" frameborder="0" scrolling="no"></iframe>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('slideshare');
				}
			],
			[
				// Taken from the "WordPress Code" button of the page
				'[soundcloud url="http://api.soundcloud.com/tracks/98282116" params="" width=" 100%" height="166" iframe="true" /]',
				'<iframe width="560" height="166" allowfullscreen="" frameborder="0" scrolling="no" src="https://w.soundcloud.com/player/?url=http://api.soundcloud.com/tracks/98282116"></iframe>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('soundcloud');
				}
			],
			[
				'[soundcloud url="https://api.soundcloud.com/tracks/12345?secret_token=s-foobar" width="100%" height="166" iframe="true" /]',
				'<iframe width="560" height="166" allowfullscreen="" frameborder="0" scrolling="no" src="https://w.soundcloud.com/player/?url=https://api.soundcloud.com/tracks/12345?secret_token=s-foobar&amp;secret_token=s-foobar"></iframe>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('soundcloud');
				}
			],
			[
				'https://soundcloud.com/andrewbird/three-white-horses',
				'<iframe width="560" height="166" allowfullscreen="" frameborder="0" scrolling="no" src="https://w.soundcloud.com/player/?url=https://soundcloud.com/andrewbird/three-white-horses"></iframe>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('soundcloud');
				}
			],
			[
				'https://soundcloud.com/tenaciousd/sets/rize-of-the-fenix/',
				'<iframe width="560" height="166" allowfullscreen="" frameborder="0" scrolling="no" src="https://w.soundcloud.com/player/?url=https://soundcloud.com/tenaciousd/sets/rize-of-the-fenix"></iframe>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('soundcloud');
				}
			],
			[
				'[soundcloud url="https://api.soundcloud.com/playlists/1919974" width="100%" height="450" iframe="true" /]',
				'<iframe width="560" height="166" allowfullscreen="" frameborder="0" scrolling="no" src="https://w.soundcloud.com/player/?url=https://api.soundcloud.com/playlists/1919974"></iframe>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('soundcloud');
				}
			],
			[
				'[spotify]spotify:track:5JunxkcjfCYcY7xJ29tLai[/spotify]',
				'<iframe width="300" height="80" allowfullscreen="" frameborder="0" scrolling="no" src="https://embed.spotify.com/?uri=spotify:track:5JunxkcjfCYcY7xJ29tLai"></iframe>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('spotify');
				}
			],
			[
				'https://play.spotify.com/album/5OSzFvFAYuRh93WDNCTLEz',
				'<iframe width="300" height="380" allowfullscreen="" frameborder="0" scrolling="no" src="https://embed.spotify.com/?uri=spotify:album:5OSzFvFAYuRh93WDNCTLEz"></iframe>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('spotify');
				}
			],
			[
				'http://www.ted.com/talks/eli_pariser_beware_online_filter_bubbles.html',
				'<iframe width="560" height="315" src="http://embed.ted.com/talks/eli_pariser_beware_online_filter_bubbles.html" allowfullscreen="" frameborder="0" scrolling="no"></iframe>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('ted');
				}
			],
			[
				'http://www.twitch.tv/minigolf2000',
				'<object type="application/x-shockwave-flash" typemustmatch="" width="620" height="378" data="http://www.twitch.tv/widgets/live_embed_player.swf"><param name="allowfullscreen" value="true"><param name="flashvars" value="channel=minigolf2000"><embed type="application/x-shockwave-flash" width="620" height="378" src="http://www.twitch.tv/widgets/live_embed_player.swf" allowfullscreen=""></object>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('twitch');
				}
			],
			[
				'http://www.twitch.tv/minigolf2000/c/2475925',
				'<object type="application/x-shockwave-flash" typemustmatch="" width="620" height="378" data="http://www.twitch.tv/widgets/archive_embed_player.swf"><param name="allowfullscreen" value="true"><param name="flashvars" value="channel=minigolf2000&amp;chapter_id=2475925"><embed type="application/x-shockwave-flash" width="620" height="378" src="http://www.twitch.tv/widgets/archive_embed_player.swf" allowfullscreen=""></object>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('twitch');
				}
			],
			[
				'http://www.twitch.tv/minigolf2000/b/419320018',
				'<object type="application/x-shockwave-flash" typemustmatch="" width="620" height="378" data="http://www.twitch.tv/widgets/archive_embed_player.swf"><param name="allowfullscreen" value="true"><param name="flashvars" value="channel=minigolf2000&amp;archive_id=419320018"><embed type="application/x-shockwave-flash" width="620" height="378" src="http://www.twitch.tv/widgets/archive_embed_player.swf" allowfullscreen=""></object>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('twitch');
				}
			],
			[
				'http://www.ustream.tv/recorded/40771396',
				'<iframe width="480" height="302" allowfullscreen="" frameborder="0" scrolling="no" src="http://www.ustream.tv/embed/recorded/40771396"></iframe>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('ustream');
				}
			],
			[
				'http://vimeo.com/67207222',
				'<iframe width="560" height="315" src="//player.vimeo.com/video/67207222" allowfullscreen="" frameborder="0" scrolling="no"></iframe>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('vimeo');
				}
			],
			[
				'https://vine.co/v/bYwPIluIipH',
				'<iframe width="480" height="480" src="https://vine.co/v/bYwPIluIipH/embed/simple" allowfullscreen="" frameborder="0" scrolling="no"></iframe><script async="" src="//platform.vine.co/static/scripts/embed.js" charset="utf-8"></script>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('vine');
				}
			],
			[
				'http://www.worldstarhiphop.com/videos/video.php?v=wshhZ8F22UtJ8sLHdja0',
				'<object type="application/x-shockwave-flash" typemustmatch="" width="448" height="374" data="http://www.worldstarhiphop.com/videos/e/16711680/wshhZ8F22UtJ8sLHdja0"><param name="allowfullscreen" value="true"><embed type="application/x-shockwave-flash" src="http://www.worldstarhiphop.com/videos/e/16711680/wshhZ8F22UtJ8sLHdja0" width="448" height="374" allowfullscreen=""></object>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('wshh');
				}
			],
			[
				'http://m.worldstarhiphop.com/video.php?v=wshh2SXFFe7W14DqQx61',
				'<object type="application/x-shockwave-flash" typemustmatch="" width="448" height="374" data="http://www.worldstarhiphop.com/videos/e/16711680/wshh2SXFFe7W14DqQx61"><param name="allowfullscreen" value="true"><embed type="application/x-shockwave-flash" src="http://www.worldstarhiphop.com/videos/e/16711680/wshh2SXFFe7W14DqQx61" width="448" height="374" allowfullscreen=""></object>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('wshh');
				}
			],
			[
				'[media=youtube]-cEzsCAzTak[/media]',
				'<iframe width="560" height="315" allowfullscreen="" frameborder="0" scrolling="no" src="//www.youtube.com/embed/-cEzsCAzTak"></iframe>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('youtube');
				}
			],
			[
				'[media]http://www.youtube.com/watch?v=-cEzsCAzTak&feature=channel[/media]',
				'<iframe width="560" height="315" allowfullscreen="" frameborder="0" scrolling="no" src="//www.youtube.com/embed/-cEzsCAzTak"></iframe>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('youtube');
				}
			],
			[
				'[YOUTUBE]-cEzsCAzTak[/YOUTUBE]',
				'<iframe width="560" height="315" allowfullscreen="" frameborder="0" scrolling="no" src="//www.youtube.com/embed/-cEzsCAzTak"></iframe>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('youtube');
				}
			],
			[
				'[YOUTUBE]http://www.youtube.com/watch?v=-cEzsCAzTak&feature=channel[/YOUTUBE]',
				'<iframe width="560" height="315" allowfullscreen="" frameborder="0" scrolling="no" src="//www.youtube.com/embed/-cEzsCAzTak"></iframe>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('youtube');
				}
			],
			[
				'[YOUTUBE=http://www.youtube.com/watch?v=-cEzsCAzTak]Hi!',
				'<iframe width="560" height="315" allowfullscreen="" frameborder="0" scrolling="no" src="//www.youtube.com/embed/-cEzsCAzTak"></iframe>Hi!',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('youtube');
				}
			],
			[
				'Check this: http://www.youtube.com/watch?v=-cEzsCAzTak',
				'Check this: <iframe width="560" height="315" allowfullscreen="" frameborder="0" scrolling="no" src="//www.youtube.com/embed/-cEzsCAzTak"></iframe>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('youtube');
				}
			],
			[
				'Check this: http://www.youtube.com/watch?v=-cEzsCAzTak and that: http://example.com',
				'Check this: <iframe width="560" height="315" allowfullscreen="" frameborder="0" scrolling="no" src="//www.youtube.com/embed/-cEzsCAzTak"></iframe> and that: <a href="http://example.com">http://example.com</a>',
				[],
				function ($configurator)
				{
					$configurator->Autolink;
					$configurator->MediaEmbed->add('youtube');
				}
			],
			[
				'http://www.youtube.com/watch?feature=player_detailpage&v=9bZkp7q19f0#t=113',
				'<iframe width="560" height="315" allowfullscreen="" frameborder="0" scrolling="no" src="//www.youtube.com/embed/9bZkp7q19f0?start=113"></iframe>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('youtube');
				}
			],
			[
				'http://www.youtube.com/watch?v=pC35x6iIPmo&list=PLOU2XLYxmsIIxJrlMIY5vYXAFcO5g83gA',
				'<iframe width="560" height="315" allowfullscreen="" frameborder="0" scrolling="no" src="//www.youtube.com/embed/pC35x6iIPmo?list=PLOU2XLYxmsIIxJrlMIY5vYXAFcO5g83gA"></iframe>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('youtube');
				}
			],
			[
				'http://www.youtube.com/watch?v=pC35x6iIPmo&list=PLOU2XLYxmsIIxJrlMIY5vYXAFcO5g83gA#t=123',
				'<iframe width="560" height="315" allowfullscreen="" frameborder="0" scrolling="no" src="//www.youtube.com/embed/pC35x6iIPmo?list=PLOU2XLYxmsIIxJrlMIY5vYXAFcO5g83gA&amp;start=123"></iframe>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('youtube');
				}
			],
		];
	}
}