<?php

namespace Chophper\Tests;

use Chophper\Full;
use PHPUnit\Framework\TestCase;

class FullTest extends TestCase
{
	/**
	 * @dataProvider generalTruncationProvider
	 * @return void
	 */
	public function testGeneralTruncation($html, $length, $options, $expected)
	{
		$actual = Full::truncate($html, $length, $options);
		$this->assertEquals($expected, $actual, "Input HTML: " . $html);
	}

	/**
	 * @return mixed[]
	 */
	public function generalTruncationProvider()
	{
		return [
			'truncate words basic' => [
				'<p>This is a <strong>sample</strong> sentence.</p>',
				4,
				['truncateBy' => 'words'],
				'<p>This is a <strong>sample</strong>…</p>'
			],
			'truncate words shorter than length' => [
				'<p>Short sentence.</p>',
				5,
				['truncateBy' => 'words'],
				'<p>Short sentence.</p>'
			],
			'truncate words with exact length' => [
				'<p>One two three.</p>',
				3,
				['truncateBy' => 'words'],
				'<p>One two three…</p>'
			],
			'truncate words with exact length and trailing space' => [
				'<p>One two three. </p>',
				3,
				['truncateBy' => 'words'],
				'<p>One two three…</p>'
			],
			'truncate words, zero length' => [
				'<p>Content here.</p>',
				0,
				['truncateBy' => 'words'
				],
				'<p>…</p>'
			],
			'truncate words, zero length, no ellipsable tag' => [
				'Content here.',
				0,
				['truncateBy' => 'words'
				],
				'…'
			],
			'truncate chars basic (preserveWords=false by default)' => [
				'<p>Example text for <b>chars</b>.</p>',
				15,
				['truncateBy' => 'chars'
				],
				'<p>Example text fo…</p>'
			],
			'truncate chars preserveWords=true' => [
				'<p>Example text for <b>chars</b>.</p>',
				15,
				['truncateBy' => 'chars', 'preserveWords' => true
				],
				'<p>Example text<b>cha</b>…</p>'
			],
			'truncate chars shorter than length' => [
				'<p>Short.</p>',
				10,
				['truncateBy' => 'chars'],
				'<p>Short.</p>'
			],
			'truncate sentences basic' => [
				'<p>First sentence. Second sentence! Third?</p>',
				2,
				['truncateBy' => 'sentences'],
				'<p>First sentence. Second sentence…</p>'
			],
			'truncate sentences with complex punctuation' => [
				'Sentence one... Sentence two!! Sentence three. ',
				2,
				['truncateBy' => 'sentences'],
				'Sentence one... Sentence two…'
			],
			'truncate blocks basic' => [
				'<h1>Title</h1><p>First block.</p><p>Second block.</p><div>Third block</div>',
				2,
				['truncateBy' => 'blocks'],
				'<p>First block.</p><p>Second block.…</p>'
			],
			'truncate blocks, custom ellipsis' => [
				'<p>First block.</p><p>Second block.</p>',
				1,
				['truncateBy' => 'blocks', 'ellipsis' => ' (more)'
				],
				'<p>First block. (more)</p>'
			],
			'empty input string' => [
				'',
				10,
				['truncateBy' => 'words'],
				''
			],
			'html fragment' => [
				'Just <b>some</b> text.',
				2,
				['truncateBy' => 'words'],
				'Just <b>some</b>…'
			],
			'self-closing tags preserved' => [
				'<p>Text with <br/>a break and <hr/> a rule.</p>',
				6,
				['truncateBy' => 'words'],
				'<p>Text with <br/>a break and </p><hr/> a…'
			],
			'truncate with utf8 characters' => [
				'<p>こんにちは世界, this is a test.</p>',
				2,
				['truncateBy' => 'words'],
				'<p>こんにちは世界, this…</p>'
			],
			'truncate chars with utf8 (preserveWords=false)' => [
				'<span>こんにちは</span>',
				3,
				['truncateBy' => 'chars'],
				'<span>こんに</span>…'
			]
		];
	}
}
