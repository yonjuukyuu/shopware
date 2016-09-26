<?php
namespace Shopware\Tests\Unit\Components\Plugin;

use PHPUnit\Framework\TestCase;
use Shopware\Components\Model\ModelManager;
use Shopware\Components\Model\ModelRepository;
use Shopware\Components\Plugin\RequirementValidator;
use Shopware\Components\Plugin\XmlPluginInfoReader;
use Shopware\Models\Plugin\Plugin;

class RequirementValidatorTest extends TestCase
{
    /**
     * @var Plugin[]
     */
    private $plugins;

    protected function setUp()
    {
        parent::setUp();
        $this->plugins = [];
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage Plugin requires at least Shopware version 5.1.0
     */
    public function testMinimumShopwareVersionShouldFail()
    {
        $validator = $this->getValidator([]);
        $validator->validate(__DIR__ . '/examples/shopware_version_requirement.xml', '4.0.0');
    }

    public function testMinimumShopwareVersionShouldBeSuccessful()
    {
        $validator = $this->getValidator([]);
        $e = null;
        try {
            $validator->validate(__DIR__ . '/examples/shopware_version_requirement.xml', '5.1.0');
        } catch (\Exception $e) {
        }

        $this->assertNull($e);
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage Plugin is only compatible with Shopware version <= 5.2
     */
    public function testMaximumShopwareVersionShouldFail()
    {
        $validator = $this->getValidator([]);
        $validator->validate(__DIR__ . '/examples/shopware_version_requirement.xml', '5.3');
    }

    public function testMaximumShopwareVersionShouldBeSuccessful()
    {
        $validator = $this->getValidator([]);
        $e = null;
        try {
            $validator->validate(__DIR__ . '/examples/shopware_version_requirement.xml', '5.1.0');
        } catch (\Exception $e) {
        }
        $this->assertNull($e);
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage Shopware version 5.1.2 is blacklisted by the plugin
     */
    public function testBlackListedShopwareVersionShouldFail()
    {
        $validator = $this->getValidator([]);
        $validator->validate(__DIR__ . '/examples/shopware_version_requirement.xml', '5.1.2');
    }

    public function testBlackListedShopwareVersionShouldSuccessful()
    {
        $validator = $this->getValidator([]);
        $e = null;
        try {
            $validator->validate(__DIR__ . '/examples/shopware_version_requirement.xml', '5.1.3');
        } catch (\Exception $e) {
        }
        $this->assertNull($e);
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage Required plugin SwagBundle was not found
     */
    public function testRequiredPluginNotExists()
    {
        $validator = $this->getValidator([]);
        $validator->validate(__DIR__ . '/examples/shopware_required_plugin.xml', '5.2');
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage Required plugin SwagLiveShopping was not found
     */
    public function testSecondRequiredPluginNotExists()
    {
        $validator = $this->getValidator([
            ['name' => 'SwagBundle', 'version' => '2.5', 'active' => true, 'installed' => '2016-01-01 11:00:00']
        ]);
        $validator->validate(__DIR__ . '/examples/shopware_required_plugin.xml', '5.2');
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage Required plugin SwagBundle is not installed
     */
    public function testRequiredPluginInstalledShouldFail()
    {
        $validator = $this->getValidator([
            ['name' => 'SwagBundle', 'version' => '1.0', 'active' => false, 'installed' => null]
        ]);
        $validator->validate(__DIR__ . '/examples/shopware_required_plugin.xml', '5.2');
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage Required plugin SwagBundle is not active
     */
    public function testRequiredPluginActiveShouldFail()
    {
        $validator = $this->getValidator([
            ['name' => 'SwagBundle', 'active' => false, 'version' => '1.0', 'installed' => '2016-01-01 11:00:00']
        ]);
        $validator->validate(__DIR__ . '/examples/shopware_required_plugin.xml', '5.2');
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage Version 2.0 of plugin SwagBundle is required.
     */
    public function testRequiredPluginMinimumVersionShouldFail()
    {
        $validator = $this->getValidator([
            ['name' => 'SwagBundle', 'version' => '1.0', 'active' => true, 'installed' => '2016-01-01 11:00:00']
        ]);
        $validator->validate(__DIR__ . '/examples/shopware_required_plugin.xml', '5.2');
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage Plugin is only compatible with Plugin SwagBundle version <= 3.0
     */
    public function testRequiredPluginMaximumVersionShouldFail()
    {
        $validator = $this->getValidator([
            ['name' => 'SwagBundle', 'version' => '10.0', 'active' => true, 'installed' => '2016-01-01 11:00:00']
        ]);
        $validator->validate(__DIR__ . '/examples/shopware_required_plugin.xml', '5.2');
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage Required plugin SwagBundle with version 2.1 is blacklist
     */
    public function testRequiredPluginVersionIsBlackListed()
    {
        $validator = $this->getValidator([
            ['name' => 'SwagBundle', 'version' => '2.1', 'active' => true, 'installed' => '2016-01-01 11:00:00']
        ]);
        $validator->validate(__DIR__ . '/examples/shopware_required_plugin.xml', '5.2');
    }

    public function testRequiredPluginsShouldBeSuccessful()
    {
        $validator = $this->getValidator([
            ['name' => 'SwagBundle', 'version' => '2.1.1', 'active' => true, 'installed' => '2016-01-01 11:00:00'],
            ['name' => 'SwagLiveShopping', 'version' => '2.1.1', 'active' => true, 'installed' => '2016-01-01 11:00:00']
        ]);

        $e = null;
        try {
            $validator->validate(__DIR__ . '/examples/shopware_required_plugin.xml', '5.2');
        } catch (\Exception $e) {
        }
        $this->assertNull($e);
    }

    private function getValidator(array $plugins)
    {
        $em = $this->createMock(ModelManager::class);

        $repo = $this->createMock(ModelRepository::class);
        $defaults = ['active' => false, 'installed' => null];

        foreach ($plugins as $pluginInfo) {
            $pluginInfo = array_merge($defaults, $pluginInfo);

            $plugin = $this->createMock(Plugin::class);

            $plugin->method('getVersion')
                ->willReturn($pluginInfo['version']);

            $plugin->method('getName')
                ->willReturn($pluginInfo['name']);

            $plugin->method('getActive')
                ->willReturn($pluginInfo['active']);

            $plugin->method('getInstalled')
                ->willReturn($pluginInfo['installed']);

            $this->plugins[$pluginInfo['name']] = $plugin;
        }

        if ($plugins) {
            $repo->method('findOneBy')
                ->will($this->returnCallback([$this, 'findPluginByName']));
        }

        $em->method('getRepository')
           ->willReturn($repo);

        return new RequirementValidator($em, new XmlPluginInfoReader());
    }

    /**
     * @param $args
     * @return null|Plugin
     */
    public function findPluginByName($args)
    {
        $name = $args['name'];
        if (isset($this->plugins[$name])) {
            return $this->plugins[$name];
        }
        return null;
    }
}
