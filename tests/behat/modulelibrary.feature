@block @block_modulelibrary @javascript
Feature: Copy modules from template courses using block_modulelibrary
  In order to reuse prepared learning activities
  As a teacher editing a target course
  I need to browse template courses and copy modules into my course.

  Background:
    Given the following "categories" exist:
      | name      | category | idnumber  |
      | Templates | 0        | templates |
    And the following "courses" exist:
      | fullname          | shortname | category  |
      | Template Course A | TCA       | templates |
      | Template Course B | TCB       | templates |
      | Target Course     | TARGET    | 0         |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | TARGET | editingteacher |
    And the following "activities" exist:
      | activity | course | section | name        |
      | quiz     | TCA    | 1       | Test Quiz A |
      | book     | TCA    | 2       | Test Book A |
      | book     | TCB    | 2       | Test Book B |
    And the modulelibrary template category is set to "Templates"

  Scenario: Teacher can browse template courses and copy a module
    Given I log in as "admin"
    And I am on course index page for course "Target Course"
    And I turn editing mode on
    And I add the "Module Library" block
    And I log out
    When I am logged in as "teacher1"
    And I am on course homepage
    Then I should see "Template Course A" in the "Select template course" "select"
    And I should see "Template Course B" in the "Select template course" "select"
    When I select "Template Course A" from the "Select template course" singleselect
    Then I should see "Test Quiz A"
    And I should see "Test Book A"
    And I should not see "Test Book B"
    When I press "Copy this module" in the "Test Quiz A" "text"
    And I select "Section 1" from the "target-section" singleselect
    And I press "Confirm copy"
    And I wait until the page is ready
    Then the field "Select template course" matches value "Template Course A"
    And I should see activity "Test Quiz A" in section "Section 1"

  Scenario: Block is visible only in edit mode
    Given I log in as "admin"
    And I am on course index page for course "Target Course"
    And I turn editing mode on
    And I add the "Module Library" block
    And I log out
    When I am logged in as "teacher1"
    And I am on course homepage
    Then I should not see "Select template course"
    When I turn editing mode on
    Then I should see "Select template course"
