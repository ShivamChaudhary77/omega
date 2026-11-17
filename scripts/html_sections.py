#!/usr/bin/env python3
"""Split an HTML page into its top-level <body> elements, keeping each
element's exact source text. Used by the dynamic-site migration to turn the
hand-written pages into blocks without changing a single character of their
markup.
"""
import re
from html.parser import HTMLParser

VOID = {"area", "base", "br", "col", "embed", "hr", "img", "input", "link", "meta",
        "param", "source", "track", "wbr"}


class _TopLevel(HTMLParser):
    def __init__(self, text):
        super().__init__(convert_charrefs=False)
        self.text = text
        self.line_offsets = [0]
        for m in re.finditer("\n", text):
            self.line_offsets.append(m.end())
        self.depth = 0
        self.start = None
        self.parts = []  # (start, end) offsets of depth-0 elements

    def _offset(self):
        line, col = self.getpos()
        return self.line_offsets[line - 1] + col

    def handle_starttag(self, tag, attrs):
        if self.depth == 0:
            self.start = self._offset()
        if tag in VOID:
            if self.depth == 0:
                self.parts.append((self.start, self._offset() + len(self.get_starttag_text())))
            return
        self.depth += 1

    def handle_startendtag(self, tag, attrs):
        if self.depth == 0:
            start = self._offset()
            self.parts.append((start, start + len(self.get_starttag_text())))

    def handle_endtag(self, tag):
        if tag in VOID:
            return
        self.depth -= 1
        if self.depth == 0 and self.start is not None:
            end = self.text.index(">", self._offset()) + 1
            self.parts.append((self.start, end))
            self.start = None


def body_inner(html):
    m = re.search(r"<body[^>]*>(.*)</body>", html, re.S)
    if not m:
        raise ValueError("no <body>")
    return m.group(1)


def top_level_elements(fragment):
    """Exact source of each top-level element in an HTML fragment, in order.
    Comments and whitespace between elements are dropped."""
    parser = _TopLevel(fragment)
    parser.feed(fragment)
    parser.close()
    return [fragment[a:b] for a, b in parser.parts]


if __name__ == "__main__":
    import sys
    from pathlib import Path
    for path in sys.argv[1:]:
        html = Path(path).read_text(encoding="utf-8")
        print(f"## {path}")
        for el in top_level_elements(body_inner(html)):
            first = el.split(">", 1)[0][:110].replace("\n", " ")
            hints = [k for k in ("navbar", "footer", "newsletter", "accordion", "project-carousel", "HOME-BLOG",
                                 "blog-grid", "blog-cms-section", "breadcrumb", "back-to-top", "<form", "hero")
                     if k in el]
            print(f"  {len(el):6d}  {first}>  {hints}")
