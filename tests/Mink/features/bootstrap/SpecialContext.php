<?php

namespace Shopware\Tests\Mink;

use Behat\Gherkin\Node\PyStringNode;
use Behat\Mink\WebAssert;
use SensioLabs\Behat\PageObjectExtension\PageObject\Page;
use Shopware\Tests\Mink\Page\Homepage;
use Shopware\Tests\Mink\Element\MultipleElement;

class SpecialContext extends SubContext
{
    /**
     * @Given /^the articles from "(?P<name>[^"]*)" have tax id (?P<num>\d+)$/
     */
    public function theArticlesFromHaveTaxId($supplier, $taxId)
    {
        $sql = sprintf(
            'UPDATE s_articles SET taxID = %d WHERE supplierID =
                (SELECT id FROM s_articles_supplier WHERE name = "%s")',
            $taxId,
            $supplier
        );

        $this->getService('db')->exec($sql);
    }

    /**
     * @Given /^I am on the (page "[^"]*")$/
     * @When /^I go to the (page "[^"]*")$/
     */
    public function iAmOnThePage(Page $page)
    {
        $page->open();
    }

    /**
     * @Then /^I should be on the (page "[^"]*")$/
     */
    public function iShouldBeOnThePage(Page $page)
    {
        $page->verifyPage();
    }

    /**
     * @Then /^I should see (?P<quantity>\d+) element of type "(?P<elementClass>[^"]*)"$/
     * @Then /^I should see (?P<quantity>\d+) elements of type "(?P<elementClass>[^"]*)"$/
     */
    public function iShouldSeeElementsOfType($count, $elementClass)
    {
        /** @var Homepage $page */
        $page = $this->getPage('Homepage');
        $elements = $this->getMultipleElement($page, $elementClass);
        Helper::assertElementCount($elements, $count);
    }

    /**
     * @When /^I follow the link "(?P<linkName>[^"]*)" of the (page "[^"]*")$/
     */
    public function iFollowTheLinkOfThePage($linkName, Page $page)
    {
        Helper::clickNamedLink($page, $linkName);
    }

    /**
     * @When /^I follow the link of the element "(?P<elementClass>[^"]*)"$/
     * @When /^I follow the link of the element "(?P<elementClass>[^"]*)" on position (?P<position>\d+)$/
     */
    public function iFollowTheLinkOfTheElement($elementClass, $position = 1)
    {
        $this->getSession()->wait(5000, "$('.emotion--element').length > 0");
        $this->iFollowTheLinkOfTheElementOnPosition(null, $elementClass, $position);
    }

    /**
     * @When /^I follow the link "(?P<linkName>[^"]*)" of the element "(?P<elementClass>[^"]*)"$/
     * @When /^I follow the link "(?P<linkName>[^"]*)" of the element "(?P<elementClass>[^"]*)" on position (?P<position>\d+)$/
     */
    public function iFollowTheLinkOfTheElementOnPosition($linkName, $elementClass, $position = 1)
    {
        /** @var HelperSelectorInterface $element */
        $element = $this->getElement($elementClass);

        if ($element instanceof MultipleElement) {
            /** @var Homepage $page */
            $page = $this->getPage('Homepage');

            /** @var MultipleElement $element */
            $element->setParent($page);

            $element = $element->setInstance($position);
        }

        if (empty($linkName)) {
            $element->click();
            return;
        }

        Helper::clickNamedLink($element, $linkName);
    }

    /**
     * @Given /^the "(?P<field>[^"]*)" field should contain:$/
     */
    public function theFieldShouldContain($field, PyStringNode $string)
    {
        $assert = new WebAssert($this->getSession());
        $assert->fieldValueEquals($field, $string->getRaw());
    }

    /**
     * @When /^I press the button "([^"]*)" of the element "([^"]*)" on position (\d+)$/
     */
    public function iPressTheButtonOfTheElementOnPosition($linkName, $elementClass, $position = 1)
    {
        /** @var HelperSelectorInterface $element */
        $element = $this->getElement($elementClass);

        if ($element instanceof MultipleElement) {
            /** @var Homepage $page */
            $page = $this->getPage('Homepage');

            /** @var MultipleElement $element */
            $element->setParent($page);

            $element = $element->setInstance($position);
        }

        if (empty($linkName)) {
            $element->press();
            return;
        }

        Helper::pressNamedButton($element, $linkName);
    }
}
