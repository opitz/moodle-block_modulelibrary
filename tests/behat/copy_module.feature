@block @block_modulelibrary
Feature: Copy modules from template course to a new course

  Background:
    Given the following "categories" exist:
      | name      | parent  | idnumber               |
      | Courses   | 0       | Courses                |
      | Templates | 0       | Templates              |
    And the following "courses" exist:
      | fullname        | shortname | category  | format |
      | Course 1        | C1        | Courses   | topics |
      | Template Course | TC1       | Templates | topics |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student1@example.com |
    And the following "course enrolments" exist:
      | user      | course | role           |
      | teacher1  | C1     | editingteacher |
      | student1  | C1     | student        |
    And I log in as "admin"
    And I add a assign activity to course "Template Course" section "2" and I fill the form with:
      | Assignment name         | Test assignment                                |
      | Description             | Test assignment description                    |
      | Maximum grade           | 100                                            |
    And I add a quiz activity to course "Template Course" section "3" and I fill the form with:
      | Name                    | Test quiz                                      |
      | Description             | Test quiz description                          |
      | Grade to pass           | 8                                              |

@javascript
  Scenario: Add a template category and course, then copy modules to a new course
    Given the template category is set to "Templates"
    And I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add the "Module Library" block
    Then I should see "Module Library" in the "Module Library" "block"


  When I use the "block_modulelibrary" block to copy modules from "Template Course"
    Then I should see "Assignment" in the course "New Course"
    And I should see "Quiz" in the course "New Course"
