@block @block_modulelibrary @javascript
Feature: Use module library block in a course
  In order to copy template activities into a course
  As a teacher editing the course
  I need to load template modules and open the copy form

  Background:
    Given the following "categories" exist:
      | name              | idnumber |
      | Template Category | TPLCAT   |
      | Target Category   | TGTCAT   |
    And the following "courses" exist:
      | fullname        | shortname | category |
      | Template Course | TPL101    | TPLCAT   |
      | Target Course   | TGT101    | TGTCAT   |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | One      | teacher1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | TGT101 | editingteacher |
    And the following "activities" exist:
      | activity | name            | course | section |
      | quiz     | Template Quiz 1 | TPL101 | 1       |
    And I log in as "admin"
    And I set the following administration settings values:
      | setting                              | value             |
      | Template category | Template Category |
    And I log out

  Scenario: Teacher can load template modules and open copy form
    Given I am on the "Target Course" course page logged in as teacher1
    And I turn editing mode on
    And I add the "Module Library" block
    When I set the field "modulelibrary-course-select" to "Template Course"
    Then I should see "Template Quiz 1"
    When I click on ".select-template-module-btn" "css_element"
    Then I should see "Selected module:"
    And I should see "Template Quiz 1"
