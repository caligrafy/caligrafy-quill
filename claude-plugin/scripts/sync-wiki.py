#!/usr/bin/env python3
"""
Refreshes the wiki mirrors of the caligrafy skill (skills/caligrafy/reference/*.md) from a clone of the wiki.

    git clone https://github.com/caligrafy/caligrafy-quill.wiki.git
    python3 claude-plugin/scripts/sync-wiki.py ../caligrafy-quill.wiki          # write the mirrors
    python3 claude-plugin/scripts/sync-wiki.py ../caligrafy-quill.wiki --check  # exit 1 if a mirror is out of date

For every mirror, the header (title, source line) and the "Verified against framework source" note are kept;
the body is replaced by the cleaned wiki page: HTML anchors and <br/> spacers are removed and video
thumbnails become "(video)" links. api-reference.md and gotchas.md are written by hand from the source.
"""
import os
import re
import sys

REFERENCE = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'skills', 'caligrafy', 'reference')
WIKI_URL = 'https://github.com/caligrafy/caligrafy-quill/wiki'

# mirror file -> (wiki page file, title)
PAGES = {
    '00-home-overview.md': ('Home.md', 'Caligrafy Quill - Home / Overview'),
    '01-getting-started.md': ('1.-Getting-Started.md', '1. Getting Started'),
    '02-routing.md': ('2.-Routing.md', '2. Routing'),
    '03-request.md': ('3.-Request.md', '3. Request'),
    '04-models.md': ('4.-Models.md', '4. Models'),
    '05-relationships.md': ('5.-Relationships.md', '5. Relationships'),
    '06-validation.md': ('6.-Validation.md', '6. Validation'),
    '07-views.md': ('7.-Views.md', '7. Views'),
    '08-controllers.md': ('8.-Controllers.md', '8. Controllers'),
    '09-helpers-forms-rest-api.md': ('9.-Helpers-,-Forms-&-REST-API.md', '9. Helpers, Forms & REST API'),
    '09.1-authentication.md': ('9.1-Authentication.md', '9.1 Authentication'),
    '09.2-stripe-crypto-payment.md': ('9.2-Stripe-&-Cryptocurrency-Payment.md', '9.2 Stripe & Cryptocurrency Payment'),
    '09.3-metadata-rich-cards.md': ('9.3-Metadata-&-Social-Rich-Cards.md', '9.3 Metadata & Social Rich Cards'),
    '09.4-search-referencing-analytics.md': ('9.4-Search-Referencing-and-Analytics.md', '9.4 Search Referencing and Analytics'),
    '09.5-security-and-upgrading.md': ('9.5-Security-and-Upgrading.md', '9.5 Security and Upgrading'),
    '09.7-vue-flow-of-information.md': ('9.7-Understanding-the-flow-of-information.md', '9.7 Understanding the flow of information'),
    '09.7.0-vue-as-library-setup.md': ('9.7.0-Setting-up-Vue.js-as-a-library.md', '9.7.0 Setting up Vue.js as a library'),
    '09.7.1-vue-structure.md': ('9.7.1-Understanding-the-structure.md', '9.7.1 Understanding the structure'),
    '09.7.2-vue-routes.md': ('9.7.2-Routes.md', '9.7.2 Routes'),
    '09.7.3-vue-requests.md': ('9.7.3-Requests.md', '9.7.3 Requests'),
    '09.7.4-vue-forms.md': ('9.7.4-Forms.md', '9.7.4 Forms'),
    '09.7.5-vue-validations.md': ('9.7.5-Validations.md', '9.7.5 Validations'),
    '09.7.6-vue-components.md': ('9.7.6-Components.md', '9.7.6 Components'),
    '09.8.1-vue-spa-setup.md': ('9.8.1---Setting-up-the-Vue-application.md', '9.8.1 Setting up the Vue application'),
    '09.8.2-vue-spa-routing.md': ('9.8.2-Routing-for-Single-Page-Applications.md', '9.8.2 Routing for Single Page Applications'),
    '09.8.3-vue-single-file-components.md': ('9.8.3-Single-File-Components.md', '9.8.3 Single File Components'),
    '09.8.4-vue-state-management.md': ('9.8.4-State-Management.md', '9.8.4 State Management'),
    '09.9.1-ai-watson-bots.md': ('9.9.1-Creating-Bots-with-Watson.md', '9.9.1 Creating Bots with Watson'),
    '09.9.2-ai-face-detection.md': ('9.9.2-Face-Detection-and-Face-Recognition.md', '9.9.2 Face Detection and Face Recognition'),
    '09.9.3-ai-machine-learning.md': ('9.9.3-Machine-Learning.md', '9.9.3 Machine Learning'),
    '09.9.4-ai-openai-chatgpt-dalle.md': ('9.9.4-OpenAI:-ChatGPT,-GPTs,-DALL.E.md', '9.9.4 OpenAI: ChatGPT, GPTs, DALL.E'),
    '09.9.5-ai-openai-assistants.md': ('9.9.5-Open-AI:-Assistants.md', '9.9.5 OpenAI: Assistants'),
    '09.9.6-ai-open-source-agents-groq.md': ('9.9.6-Open-Source-Agents-(llama,-Mistral-etc.).md', '9.9.6 Open Source Agents (llama, Mistral etc.)'),
    '10-claude-code-plugin.md': ('10.-Claude-Code-Plugin.md', '10. Claude Code Plugin'),
    '11-release-notes-october-2026.md': ('Release-notes:-October-2026.md', 'Release notes: October 2026'),
    '12-testing-caligrafy.md': ('Testing-Caligrafy.md', 'Testing Caligrafy (contributors)'),
    '18-caligrafer.md': ('Caligrafer.md', 'Caligrafer: every command'),
    '15-quickstart.md': ('Quickstart.md', 'Quickstart'),
    '16-how-caligrafy-works.md': ('How-Caligrafy-Works.md', 'How Caligrafy works'),
    '17-your-first-capability.md': ('Your-First-Capability.md', 'Your first capability'),
    '14-release-notes-2.1.0.md': ('Release-notes:-2.1.0.md', 'Release notes: 2.1.0'),
    '13-build-once-services-capabilities-mcp.md': ('11.-Build-Once:-Services,-Capabilities-and-MCP.md', '11. Build Once: Services, Capabilities and MCP'),
    'wiki-sidebar.md': ('_Sidebar.md', 'Wiki sidebar (table of contents)'),
}


def clean(text):
    text = re.sub(r'<a name=[\'"][^\'"]*[\'"]>\s*(</a>)?', '', text)
    text = re.sub(r'!\[\]\(https://github\.com/caligrafy/caligrafy-quill/blob/master/public/images/resources/play\.png\)\s*', '(video) ', text)
    lines = [line for line in text.split('\n') if not re.fullmatch(r'\s*(<br\s*/?>\s*)+', line)]
    text = '\n'.join(line.rstrip() if not line.strip() else line for line in lines)
    text = re.sub(r'\n{3,}', '\n\n', text)
    return text.strip() + '\n'


def header_and_note(path, title, page):
    source = WIKI_URL if page == 'Home.md' else WIKI_URL + '/' + page[:-3]
    header = '# %s\n\n> Source: Caligrafy Quill wiki - %s\n> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.\n' % (title, source)
    note = ''
    if os.path.exists(path):
        current = open(path, encoding='utf-8').read()
        if current.startswith('# '):
            header = current.split('\n', 1)[0] + header[header.index('\n'):]
        match = re.search(r'\n(> \*\*Verified against framework source.*?)\n\n', current, re.S)
        if match:
            note = match.group(1) + '\n'
    return header, note


def main():
    if len(sys.argv) < 2:
        print(__doc__)
        return 2
    wiki, check = sys.argv[1], '--check' in sys.argv
    stale = []
    for mirror, (page, title) in PAGES.items():
        source_file = os.path.join(wiki, page)
        if not os.path.exists(source_file):
            print('missing wiki page: ' + page)
            stale.append(mirror)
            continue
        path = os.path.join(REFERENCE, mirror)
        header, note = header_and_note(path, title, page)
        content = header + '\n' + (note + '\n' if note else '') + clean(open(source_file, encoding='utf-8').read())
        current = open(path, encoding='utf-8').read() if os.path.exists(path) else None
        if current != content:
            stale.append(mirror)
            if not check:
                open(path, 'w', encoding='utf-8').write(content)
    print(('out of date: ' if check else 'updated: ') + (', '.join(stale) if stale else 'none'))
    return 1 if check and stale else 0


if __name__ == '__main__':
    sys.exit(main())
