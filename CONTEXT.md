# Craft Inertia frontend

A Craft site can present its public frontend through Inertia and Vue while
Craft continues to own its content and editorial behavior.

## Language

**Reusable plugin**:
The shared Craft extension that enables public Inertia pages across projects
with different content schemas and designs.
_Avoid_: Site implementation, theme.

**Project frontend**:
The pages, layouts and interactions that belong to one consuming Craft project.
_Avoid_: Plugin frontend when referring to a site's own presentation.

**Public frontend**:
The visitor-facing part of a Craft site, including pages restricted to signed-in
visitors. It excludes Craft's control panel.
_Avoid_: Public when the intended meaning is anonymous access only.

**Canonical entry**:
The original entry to which its drafts and revisions belong. A canonical entry
is not necessarily published or available to a visitor.
_Avoid_: Published entry as a synonym.

**Preview content**:
The version of content that Craft has authorized for an editorial preview. It
can differ from the version available through an ordinary site visit.
_Avoid_: Public content merely because it has a preview URL.

**Preview target**:
A destination an editor can choose to inspect an entry in a particular
presentation.
_Avoid_: Preview permission or draft as a synonym.

**Site variant**:
The content and URL context of one configured Craft site, which can have its
own language, domain or path.
_Avoid_: Language as a complete synonym for site.

**Compatibility integration**:
Support for named features of another plugin at specified compatible versions.
_Avoid_: Universal compatibility based only on that plugin being installed.
