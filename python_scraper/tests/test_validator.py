from validator import validate_candidate_selectors


def test_valid_dom_and_selectors_are_valid():
    html = """
    <html>
      <body>
        <article class="post">
          <h2><strong class="author">Alice</strong></h2>
          <div data-ad-preview="message">Hello</div>
          <abbr data-utime="1710000000"></abbr>
        </article>
      </body>
    </html>
    """
    selectors = {
        "post_container": "article.post",
        "author_name": "h2 strong.author",
        "text_content": 'div[data-ad-preview="message"]',
        "timestamp": 'abbr[data-utime]'
    }
    result = validate_candidate_selectors(selectors, html)
    assert result["is_valid"] is True
    assert result["coverage_score"] == 1.0


def test_invalid_css_is_invalid():
    html = "<article><strong>Alice</strong></article>"
    selectors = {
        "post_container": "article[",
        "author_name": "strong",
        "text_content": "article",
        "timestamp": "article",
    }
    result = validate_candidate_selectors(selectors, html)
    assert result["is_valid"] is False
    assert result["field_results"]["post_container"]["valid_syntax"] is False


def test_missing_required_field_is_invalid():
    html = "<article><strong>Alice</strong></article>"
    selectors = {
        "post_container": "article",
        "author_name": "strong",
        "text_content": "",
        "timestamp": "abbr",
    }
    result = validate_candidate_selectors(selectors, html)
    assert result["is_valid"] is False
    assert result["coverage_score"] < 1.0


def test_empty_html_is_unproven():
    selectors = {
        "post_container": "article",
        "author_name": "strong",
        "text_content": "div",
        "timestamp": "abbr",
    }
    result = validate_candidate_selectors(selectors, "")
    assert result["is_valid"] is False
    assert result["proven"] is False
