Summary: HTML Purifier is a standards-compliant HTML filter library written in PHP.
Name: php-htmlpurifier
Version: 4.4.0
Release: 1
License: LGPL
Group: Development/Languages
URL: http://htmlpurifier.org/

Packager: Alain PEYRAT <aljeux@free.fr>

Source: http://htmlpurifier.org/releases/htmlpurifier-%{version}.tar.gz
BuildRoot: %{_tmppath}/%{name}-%{version}-%{release}-root

BuildArch: noarch
Requires: php >= 5.0.5

Obsoletes: htmlpurifier

%description
HTML Purifier is a standards-compliant HTML filter library written in PHP. HTML
Purifier will not only remove all malicious code (better known as XSS) with a
thoroughly audited, secure yet permissive whitelist, it will also make sure
your documents are standards compliant, something only achievable with a
comprehensive knowledge of W3C's specifications. 

Tired of using BBCode due to the current landscape of deficient or insecure
HTML filters? Have a WYSIWYG editor but never been able to use it?

Looking for high-quality, standards-compliant, open-source components for that
application you're building? HTML Purifier is for you!


%package docs
Summary: Documentation for package %{name}
Group: Documentation

%description docs
HTML Purifier is a standards-compliant HTML filter library written in PHP.

This package includes the documentation for %{name}.

%prep
%setup -n htmlpurifier-%{version}

%build

%install
%{__rm} -rf %{buildroot}
%{__install} -d -m0755 %{buildroot}%{_datadir}/php
%{__cp} -ar library/* %{buildroot}%{_datadir}/php

%clean
%{__rm} -rf %{buildroot}

%files
%defattr(-, root, root, 0755)
%{_datadir}/php/

%files docs
%defattr(-, root, root, 0755)
%doc art benchmarks configdoc CREDITS docs INSTALL INSTALL.fr.utf8 LICENSE NEWS README TODO VERSION WHATSNEW WYSIWYG

%changelog
* Mon Feb 06 2012 Alain Peyrat <aljeux@free.fr>  - 4.4.0-1
- Updated to v4.4.0

* Sat Nov 20 2010 Alain Peyrat <aljeux@free.fr>  - 4.3.0-1
- Updated to v4.3.0

* Sat Nov 20 2010 Alain Peyrat <aljeux@free.fr>  - 4.2.0-1
- Renamed to php-htmlpurifier

* Thu Sep 16 2010 Alain Peyrat <aljeux@free.fr>  - 4.2.0
- Updated to v4.2.0

* Wed Jun 02 2010 Roland Mas <lolando@debian.org> - 4.1.1
- Updated to v4.1.1

* Tue Apr 27 2010 Roland Mas <lolando@debian.org> - 4.1.0
- Updated to v4.1.0

* Sun Mar 28 2010 Alain Peyrat <aljeux@free.fr> - 4.0.0-1
- Removed requirement to webserver, set php >= 5.0.5

* Thu Sep 24 2009 Alain Peyrat <aljeux@free.fr> - 4.0.0-0
- Updated to v4.0.0
- Changed installation path to /usr/share/php to use the share path with debian.

* Mon Apr 27 2009 Nicolas GUERIN <nicolas.guerin@xrce.xerox.com> - 3.3.0
- Updated to v3.3.0

* Thu Jan 29 2009 Nicolas GUERIN <nicolas.guerin@xrce.xerox.com> - 3.2.0
- Updated to v3.2.0

* Fri Jun 27 2008 Nicolas GUERIN <nicolas.guerin@xrce.xerox.com> - 3.1.1
- Updated to v3.1.1

* Thu Mar 6 2008 Nicolas GUERIN <nicolas.guerin@xrce.xerox.com> - 3.0.0
- Updated to v3.0.0

* Mon Oct 8 2007 Nicolas GUERIN <nicolas.guerin@xrce.xerox.com> - 2.1.2-0
- Updated to v2.1.2. Removed cache config change during setup.

* Tue Jul 31 2007 Manuel VACELET <manuel.vacelet@st.com> - 2.0.1-0
- Initial package.
