import json
import re
import sys
from dataclasses import dataclass, field
from html.parser import HTMLParser
from typing import Any, Dict, List, Optional, Tuple

REQUIRED_FIELDS = ["post_container", "author_name", "text_content", "timestamp"]


@dataclass
class HtmlNode:
    tag: str
    attrs: Dict[str, str]
    parent: Optional["HtmlNode"] = None
    children: List["HtmlNode"] = field(default_factory=list)


class _TreeBuilder(HTMLParser):
    def __init__(self) -> None:
        super().__init__(convert_charrefs=True)
        self.root = HtmlNode(tag="__root__", attrs={})
        self.current = self.root

    def handle_starttag(self, tag: str, attrs: List[Tuple[str, Optional[str]]]) -> None:
        node = HtmlNode(tag=tag.lower(), attrs={k.lower(): (v or "") for k, v in attrs}, parent=self.current)
        self.current.children.append(node)
        self.current = node

    def handle_startendtag(self, tag: str, attrs: List[Tuple[str, Optional[str]]]) -> None:
        node = HtmlNode(tag=tag.lower(), attrs={k.lower(): (v or "") for k, v in attrs}, parent=self.current)
        self.current.children.append(node)

    def handle_endtag(self, tag: str) -> None:
        cursor = self.current
        while cursor is not None and cursor.tag != tag.lower():
            cursor = cursor.parent
        if cursor and cursor.parent:
            self.current = cursor.parent


def _parse_html(sample_html: str) -> HtmlNode:
    parser = _TreeBuilder()
    parser.feed(sample_html or "")
    parser.close()
    return parser.root


def _tokenize_selector(selector: str) -> List[str]:
    tokens: List[str] = []
    buf = []
    depth = 0
    for ch in selector.strip():
        if ch == "[":
            depth += 1
        elif ch == "]":
            depth -= 1
        if depth == 0 and ch in (">", " "):
            if buf:
                tokens.append("".join(buf).strip())
                buf = []
            if ch == ">":
                tokens.append(">")
        else:
            buf.append(ch)
    if buf:
        tokens.append("".join(buf).strip())
    return [tok for tok in tokens if tok]


def _parse_simple_selector(selector: str) -> Dict[str, Any]:
    if not selector:
        raise ValueError("empty selector")
    pattern = re.compile(r"^(?P<tag>[a-zA-Z][a-zA-Z0-9_-]*)?(?P<rest>.*)$")
    m = pattern.match(selector.strip())
    if not m:
        raise ValueError("invalid selector syntax")
    tag = (m.group("tag") or "").lower() or None
    rest = m.group("rest") or ""
    classes: List[str] = []
    ident: Optional[str] = None
    attrs: List[Tuple[str, Optional[str]]] = []
    idx = 0
    while idx < len(rest):
        if rest[idx] == ".":
            idx += 1
            start = idx
            while idx < len(rest) and re.match(r"[a-zA-Z0-9_-]", rest[idx]):
                idx += 1
            classes.append(rest[start:idx])
        elif rest[idx] == "#":
            idx += 1
            start = idx
            while idx < len(rest) and re.match(r"[a-zA-Z0-9_-]", rest[idx]):
                idx += 1
            ident = rest[start:idx]
        elif rest[idx] == "[":
            end = rest.find("]", idx + 1)
            if end == -1:
                raise ValueError("unclosed attribute selector")
            content = rest[idx + 1:end].strip()
            if "=" in content:
                key, value = content.split("=", 1)
                value = value.strip().strip('"\'')
            else:
                key, value = content, None
            attrs.append((key.strip().lower(), value))
            idx = end + 1
        else:
            raise ValueError("unsupported selector syntax")
    return {"tag": tag, "classes": classes, "id": ident, "attrs": attrs}


def _matches_simple(node: HtmlNode, simple: Dict[str, Any]) -> bool:
    if node.tag == "__root__":
        return False
    if simple["tag"] and node.tag != simple["tag"]:
        return False
    if simple["id"] and node.attrs.get("id") != simple["id"]:
        return False
    node_classes = set((node.attrs.get("class") or "").split())
    for klass in simple["classes"]:
        if klass not in node_classes:
            return False
    for key, expected in simple["attrs"]:
        if key not in node.attrs:
            return False
        if expected is not None and node.attrs.get(key) != expected:
            return False
    return True


def _descendants(node: HtmlNode) -> List[HtmlNode]:
    out: List[HtmlNode] = []
    stack = list(node.children)
    while stack:
        cur = stack.pop(0)
        out.append(cur)
        stack[:0] = cur.children
    return out


def _match_selector(root: HtmlNode, selector: str) -> Tuple[bool, int, bool]:
    tokens = _tokenize_selector(selector)
    if not tokens:
        raise ValueError("empty selector")
    if tokens[0] == ">":
        raise ValueError("selector cannot start with child combinator")

    steps = []
    combinator = " "
    for token in tokens:
        if token == ">":
            combinator = ">"
            continue
        steps.append((combinator, _parse_simple_selector(token)))
        combinator = " "

    current = [root]
    for i, (comb, simple) in enumerate(steps):
        next_nodes: List[HtmlNode] = []
        candidates: List[HtmlNode] = []
        for node in current:
            if comb == ">":
                candidates.extend(node.children)
            else:
                candidates.extend(_descendants(node))
        for node in candidates:
            if _matches_simple(node, simple):
                next_nodes.append(node)
        current = next_nodes
        if not current:
            return False, 0, True
    return True, len(current), True


def validate_candidate_selectors(candidate_json_str: str, sample_html: str = "") -> Dict[str, Any]:
    try:
        candidate_data = json.loads(candidate_json_str) if isinstance(candidate_json_str, str) else candidate_json_str
        html = sample_html or candidate_data.get("sample_html", "")
        if not html.strip():
            return {
                "is_valid": False,
                "coverage_score": 0.0,
                "field_results": {},
                "error": "No sample HTML provided",
                "validator_engine": "PYTHON_DOM_VALIDATOR_V2",
                "proven": False,
            }

        root = _parse_html(html)
        field_results: Dict[str, Any] = {}
        matched_fields = 0
        valid_fields = 0
        for field_name in REQUIRED_FIELDS:
            selector = candidate_data.get(field_name)
            result = {"selector": selector, "valid_syntax": False, "match_count": 0, "coverage": 0.0}
            if isinstance(selector, str) and selector.strip():
                try:
                    matched, count, syntactically_valid = _match_selector(root, selector.strip())
                    result["valid_syntax"] = syntactically_valid
                    result["match_count"] = count
                    result["coverage"] = 1.0 if matched else 0.0
                    if matched and count > 0:
                        matched_fields += 1
                        valid_fields += 1
                except ValueError as exc:
                    result["error"] = str(exc)
            field_results[field_name] = result

        coverage_score = float(matched_fields) / float(len(REQUIRED_FIELDS))
        is_valid = coverage_score == 1.0 and valid_fields == len(REQUIRED_FIELDS)
        return {
            "is_valid": is_valid,
            "coverage_score": coverage_score,
            "field_results": field_results,
            "validator_engine": "PYTHON_DOM_VALIDATOR_V2",
            "proven": True,
        }
    except Exception as e:
        return {
            "is_valid": False,
            "coverage_score": 0.0,
            "error": f"Validation parsing failed: {e}",
            "validator_engine": "PYTHON_DOM_VALIDATOR_V2",
            "proven": False,
        }


if __name__ == "__main__":
    if len(sys.argv) > 1:
        raw_input = sys.argv[1]
        sample_html = sys.argv[2] if len(sys.argv) > 2 else ""
        print(json.dumps(validate_candidate_selectors(raw_input, sample_html)))
    else:
        print(json.dumps({"error": "No candidate input provided"}))
