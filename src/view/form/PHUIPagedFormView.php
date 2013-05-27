<?php

/**
 *
 * @task page   Managing Pages
 */
final class PHUIPagedFormView extends AphrontTagView {

  private $name = 'pages';
  private $pages = array();
  private $selectedPage;
  private $choosePage;
  private $nextPage;
  private $prevPage;
  private $complete;

  protected function canAppendChild() {
    return false;
  }


/* -(  Managing Pages  )----------------------------------------------------- */


  /**
   * @task page
   */
  public function addPage($key, PHUIFormPageView $page) {
    if (isset($this->pages[$key])) {
      throw new Exception("Duplicate page with key '{$key}'!");
    }
    $this->pages[$key] = $page;
    $page->setPagedFormView($this, $key);
    return $this;
  }


  /**
   * @task page
   */
  public function getPage($key) {
    if (!$this->pageExists($key)) {
      throw new Exception("No page '{$key}' exists!");
    }
    return $this->pages[$key];
  }


  /**
   * @task page
   */
  public function pageExists($key) {
    return isset($this->pages[$key]);
  }


  /**
   * @task page
   */
  protected function getPageIndex($key) {
    $page = $this->getPage($key);

    $index = 0;
    foreach ($this->pages as $target_page) {
      if ($page === $target_page) {
        break;
      }
      $index++;
    }

    return $index;
  }


  /**
   * @task page
   */
  protected function getPageByIndex($index) {
    foreach ($this->pages as $page) {
      if (!$index) {
        return $page;
      }
      $index--;
    }

    throw new Exception("Requesting out-of-bounds page '{$index}'.");
  }

  protected function getLastIndex() {
    return count($this->pages) - 1;
  }

  protected function isFirstPage(PHUIFormPageView $page) {
    return ($this->getPageIndex($page->getKey()) === 0);

  }

  protected function isLastPage(PHUIFormPageView $page) {
    return ($this->getPageIndex($page->getKey()) === (count($this->pages) - 1));
  }

  public function getSelectedPage() {
    return $this->selectedPage;
  }

  public function readFromObject($object) {
    foreach ($this->pages as $page) {
      $page->validateObjectType($object);
      $page->readFromObject($object);
    }

    return $this->processForm();
  }

  public function writeToResponse($response) {
    foreach ($this->pages as $page) {
      $page->validateResponseType($response);
      $response = $page->writeToResponse($page);
    }

    return $response;
  }

  public function readFromRequest(AphrontRequest $request) {
    $active_page = $request->getStr($this->getRequestKey('page'));

    foreach ($this->pages as $key => $page) {
      if ($key == $active_page) {
        $page->readFromRequest($request);
      } else {
        $page->readSerializedValues($request);
      }
    }

    $this->choosePage = $active_page;
    $this->nextPage = $request->getStr('__submit__');
    $this->prevPage = $request->getStr('__back__');

    return $this->processForm();
  }

  public function setName($name) {
    $this->name = $name;
    return $this;
  }


  public function getValue($page, $key, $default = null) {
    return $this->getPage($page)->getValue($key, $default);
  }

  public function setValue($page, $key, $value) {
    $this->getPage($page)->setValue($key, $value);
    return $this;
  }

  public function processForm() {
    foreach ($this->pages as $key => $page) {
      if (!$page->isValid()) {
        break;
      }
    }

    if ($this->pageExists($this->choosePage)) {
      $selected = $this->getPage($this->choosePage);
    } else {
      $selected = $this->getPageByIndex(0);
    }

    $is_attempt_complete = false;
    if ($this->prevPage) {
      $prev_index = $this->getPageIndex($selected->getKey()) - 1;;
      $index = max(0, $prev_index);
      $selected = $this->getPageByIndex($index);
    } else if ($this->nextPage) {
      $next_index = $this->getPageIndex($selected->getKey()) + 1;
      if ($next_index > $this->getLastIndex()) {
        $is_attempt_complete = true;
      }
      $index = min($this->getLastIndex(), $next_index);
      $selected = $this->getPageByIndex($index);
    }

    $validation_error = false;
    foreach ($this->pages as $key => $page) {
      if (!$page->isValid()) {
        $validation_error = true;
        break;
      }
      if ($page === $selected) {
        break;
      }
    }

    if ($is_attempt_complete && !$validation_error) {
      $this->complete = true;
    } else {
      $this->selectedPage = $page;
    }

    return $this;
  }

  public function isComplete() {
    return $this->complete;
  }

  public function getRequestKey($key) {
    return $this->name.':'.$key;
  }

  public function getTagContent() {
    $form = id(new AphrontFormView())
      ->setUser($this->getUser());

    $selected_page = $this->getSelectedPage();
    if (!$selected_page) {
      throw new Exception("No selected page!");
    }

    $form->addHiddenInput(
      $this->getRequestKey('page'),
      $selected_page->getKey());

    foreach ($this->pages as $page) {
      if ($page == $selected_page) {
        continue;
      }
      foreach ($page->getSerializedValues() as $key => $value) {
        $form->addHiddenInput($key, $value);
      }
    }

    $submit = id(new PHUIFormMultiSubmitControl());

    if (!$this->isFirstPage($selected_page)) {
      $submit->addBackButton();
    }

    if ($this->isLastPage($selected_page)) {
      $submit->addSubmitButton(pht("Save"));
    } else {
      $submit->addSubmitButton(pht("Continue \xC2\xBB"));
    }

    $form->appendChild($selected_page);
    $form->appendChild($submit);

    return $form;
  }

}