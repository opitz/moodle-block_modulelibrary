@block @block_modulelibrary
Feature: Module Library copy module
  In order to reuse template modules
  As a teacher with editing on
  I want to copy a module from a template course into my course

  Background:
    Given I am logged in as "teacher"
    And I am on the course page for "My course"

  Scenario: Copy module from template course
    When I turn editing on
    And I open the block "Module Library"
    And I select "Vanilla Test Course" from "Select template course"
    And I click "Copy this module"
    And I select "Append at end" from "Select target section"
    And I press "Confirm copy"
    Then I should see "Activity restored into target course" in the page
