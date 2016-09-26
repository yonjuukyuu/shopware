<?php
namespace Shopware\Tests\Unit\Components\Password\Encoder;

use PHPUnit\Framework\TestCase;
use Shopware\Components\Password\Encoder\PreHashed;

class PreHashedTest extends TestCase
{
    /**
     * @var PreHashed
     */
    private $hasher;

    public function setUp()
    {
        $this->hasher = new PreHashed();
    }

    /**
     * Test case
     */
    public function testGetNameShouldReturnName()
    {
        $this->assertEquals('PreHashed', $this->hasher->getName());
    }

    public function testEncodePasswordShouldNotModifyInput()
    {
        $this->assertEquals('example', $this->hasher->encodePassword('example'));
    }

    public function testRehash()
    {
        $this->assertFalse($this->hasher->isReencodeNeeded('example'));
    }

    public function testValidatePasswordForSameHashes()
    {
        $this->assertTrue($this->hasher->isPasswordValid('example', 'example'));
    }

    public function testValidatePasswordForDifferentHashes()
    {
        $this->assertFalse($this->hasher->isPasswordValid('example', 'alice'));
    }
}
