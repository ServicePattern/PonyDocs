<?php
/**
 * Eruditex skin
 *
 * @file
 * @ingroup Skins
 */

class SkinEruditex extends SkinTemplate {

	public $skinname = 'eruditex', $stylename = 'eruditex',
		$template = 'EruditexTemplate', $useHeadElement = true;

	public function initPage( OutputPage $out ) {
		parent::initPage( $out );

		/* Assures mobile devices that the site doesn't assume traditional
		 * desktop dimensions, so they won't downscale and will instead respect
		 * things like CSS's @media rules */
		$out->addHeadItem( 'viewport',
			'<meta name="viewport" content="width=device-width, initial-scale=1" />'
		);
	}

	/**
	 * @param $out OutputPage object
	 */
	function setupSkinUserCss( OutputPage $out ) {
		parent::setupSkinUserCss( $out );
		$out->addModuleStyles( 'skins.eruditex' );
	}
}

class EruditexTemplate extends BaseTemplate {
	/**
	 * Like msgWiki() but it ensures edit section links are never shown.
	 *
	 * Needed for Mediawiki 1.19 & 1.20 due to bug 36975:
	 * https://bugzilla.wikimedia.org/show_bug.cgi?id=36975
	 *
	 * @param $message Name of wikitext message to return
	 */
	function msgWikiNoEdit( $message ) {
		global $wgOut;
		global $wgParser;

		$popts = new ParserOptions();
		$popts->setEditSection( false );
		$text = wfMessage( $message )->text();
		return $wgParser->parse( $text, $wgOut->getTitle(), $popts )->getText();
	}

	/**
	 * Template filter callback for this skin.
	 * Takes an associative array of data set from a SkinTemplate-based
	 * class, and a wrapper for MediaWiki's localization database, and
	 * outputs a formatted page.
	 */
	public function execute() {

		global $action, $IP, $wgArticlePath, $wgContLang, $wgExtraNamespaces, $wgRequest, $wgRevision, $wgTitle, $wgUser;

		PonyDocsProduct::LoadProducts();
		$this->data['selectedProduct'] = PonyDocsProduct::GetSelectedProduct();
		PonyDocsProductVersion::LoadVersionsForProduct( $this->data['selectedProduct'] );
		PonyDocsProductManual::LoadManualsForProduct( $this->data['selectedProduct'] );

		$ponydocs = PonyDocsWiki::getInstance( $this->data['selectedProduct'] );

		$this->data['products'] = $ponydocs->getProductsForTemplate();
		$this->data['versions'] = $ponydocs->getVersionsForProduct( $this->data['selectedProduct'] );
		$this->data['namespaces'] = $wgExtraNamespaces;
		$this->data['selectedVersion'] = PonyDocsProductVersion::GetSelectedVersion( $this->data['selectedProduct'] );
		$this->data['pVersion'] =
			PonyDocsProductVersion::GetVersionByName( $this->data['selectedProduct'], $this->data['selectedVersion'] );
		if ( PONYDOCS_DEBUG ) {
			error_log( "DEBUG [" . __METHOD__ . "] selected product/version is set to " . $this->data['selectedProduct'] . "/"
				. $this->data['selectedVersion'] );
		}
		$this->data['versionurl'] = $this->data['wgScript'] . '?title=' . $this->data['thispage'] . '&action=changeversion';

		$this->skin = $this->data['skin'];
		// TODO remove this, and replace elsewhere (template files mostly) with $this->skin
		$skin = $this->data['skin'];
		if ( $this->skin->getTitle() ) {
			$this->data['canonicalURI'] = $this->skin->getTitle()->getFullURL();
		}

		$action = $wgRequest->getText( 'action' );

		// Suppress warnings to prevent notices about missing indexes in $this->data
		// wfSuppressWarnings();

		/**
		 * When displaying a page we output header.php, then a sub-template, and then footer.php. The namespace
		 * which we are in determines the sub-template, which is named 'ns<Namespace>'. It defaults to our
		 * nsDefault.php template. 
		 */
		$this->data['namespace'] = $wgContLang->getNsText($wgTitle->getNamespace());
		$idx = $this->data['namespace'] ? "NS:{$this->data['namespace']}" : 'T:' . $wgTitle->__toString();
		if ( !isset( $this->_methodMappings[$idx] ) ) {
			$idx = 0;
		}

		$inDocumentation = FALSE;
		if ( $this->data['namespace'] == PONYDOCS_DOCUMENTATION_NAMESPACE_NAME
			|| $wgTitle->__toString() == PONYDOCS_DOCUMENTATION_NAMESPACE_NAME
			|| preg_match( '/^' . PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . ':/', $wgTitle->__toString() ) ) {
			$inDocumentation = TRUE;
			$this->prepareDocumentation();
		}
		$this->data['versions'] = $ponydocs->getVersionsForProduct( $this->data['selectedProduct'] );

		$this->html( 'headelement' );

		?>
		<script type="text/javascript">
			function AjaxChangeProduct_callback( code, text, o ) {
				document.getElementById( 'docsProductSelect' ).disabled = true;
				var s = new String( o.responseText );
				document.getElementById( 'docsProductSelect' ).disabled = false;
				window.location.href = s;
			}

			function AjaxChangeProduct() {
				var productIndex = document.getElementById( 'docsProductSelect' ).selectedIndex;
				var product = document.getElementById( 'docsProductSelect' )[productIndex].value;
				var title = '<?php Xml::escapeJsString($this->data['thispage']); ?>';
				//sajax_do_call( 'efPonyDocsAjaxChangeProduct', [product,title], AjaxChangeProduct_callback );
				$.get(
					mw.util.wikiScript(), {
						action: 'ajax',
						rs: 'efPonyDocsAjaxChangeProduct',
						rsargs: [product,title]
					},
					AjaxChangeProduct_callback
				);
			}

			function AjaxChangeVersion_callback( code, text, o ) {
				document.getElementById( 'docsVersionSelect' ).disabled = true;
				var s = new String( o.responseText );
				document.getElementById( 'docsVersionSelect' ).disabled = false;
				window.location.href = s;
			}

			function AjaxChangeVersion() {
				var productIndex = document.getElementById( 'docsProductSelect' ).selectedIndex;
				var product = document.getElementById( 'docsProductSelect' )[productIndex].value;
				var versionIndex = document.getElementById( 'docsVersionSelect' ).selectedIndex;
				var version = document.getElementById( 'docsVersionSelect' )[versionIndex].value;
				var title = '<?php Xml::escapeJsString($this->data['thispage']); ?>';
				//sajax_do_call( 'efPonyDocsAjaxChangeVersion', [product,version,title], AjaxChangeVersion_callback );
				$.get(
					mw.util.wikiScript(), {
						action: 'ajax',
						rs: 'efPonyDocsAjaxChangeVersion',
						rsargs: [product,version,title]
					},
					AjaxChangeVersion_callback
				);
			}

			function changeManual(){
				var url = $( "#docsManualSelect" ).val();
				if ( url != "" ){
					window.location.href = url;
				}
			}

//$(document).ready(function () {
window.onload = function() {
  String.prototype.decodeHTML = function() {
    return $("<div>", {html: "" + this}).html();
  };

  var $main = $("div#content"),
  
  init = function() {
    // Do this when a page loads.
	$main.animate({"scrollTop": 0});
  },
  
  ajaxLoad = function(html) {
    document.title = html
      .match(/<title>(.*?)<\/title>/)[1]
      .trim()
      .decodeHTML();

    init();
  },
  
  loadPage = function(href) {
    $main.load(href + " div#content>*", ajaxLoad);
  };
  
  init();
  
  $(window).on("popstate", function(e) {
    if (e.originalEvent.state !== null) {
      loadPage(location.href);
    }
  });

  $(document).on("click", "a, area", function() {
    var href = $(this).attr("href");
	var base = window.location.pathname.substr(0,window.location.pathname.lastIndexOf("/"));
    if (href.substr(0,href.lastIndexOf("/")) == base && !(href.lastIndexOf(":") > href.lastIndexOf("/")) ) 
    {
      history.pushState({}, '', href);
      loadPage(href);
      return false;
    }
  });
}//);
		</script>

		<div class="mw-jump">
			<a href="#bodyContent"><?php $this->msg( 'erudite-skiptocontent' ) ?></a><?php $this->msg( 'comma-separator' ) ?>
			<a href="#search"><?php $this->msg( 'erudite-skiptosearch' ) ?></a>
		</div>

		<?php $toc=""; $docselector="";?>
		<?php ob_start(); ?>
			<div id="p-documentation" class="portlet">
				<div id="documentationBody" class="pBody">
					<?php
					if ( !count( $this->data['products'] ) ) { ?>
						<p>No products defined.</p>
						<?php
					} else { ?>
						<div class="product">
							<label for='docsProductSelect' class="navlabels">Product:&nbsp;</label>
							<select id="docsProductSelect" name="selectedProduct" onChange="AjaxChangeProduct();">
								<?php $this->hierarchicalProductSelect(); ?>
							</select>
						</div>
						<?php
							$versions = PonyDocsProductVersion::GetVersions( $this->data['selectedProduct'], TRUE );
							if ( !count( $versions ) ) {?>
								<p>No Product Versions Defined.</p>
								<?php
							} else {
								$manuals = PonyDocsProductManual::GetDefinedManuals( $this->data['selectedProduct'], TRUE );
								if ( !count( $manuals ) ) { ?>
									<p>The product manual you requested is not defined, you are not logged in,
										or you do not have the correct permissions to view this content.</p>
									<?php
								} else { ?>
										<div class="productVersion">
											<?php
											// do quick manip
											$found = FALSE;
											for ( $i =( count( $this->data['versions'] ) - 1 ); $i >= 0; $i-- ) {
												$this->data['versions'][$i]['label'] = $this->data['versions'][$i]['name'];
												if ( !$found && $this->data['versions'][$i]['status'] == "released" ) {
													$this->data['versions'][$i]['label'] .= " (latest release)";
													$found = TRUE;
												}
											} ?>
											<label for='docsVersionSelect' class="navlabels">&nbsp;Version:&nbsp;</label>
											<select id="docsVersionSelect" name="selectedVersion" onChange="AjaxChangeVersion();">
												<?php
												foreach ( $this->data['versions'] as $idx => $data ) {
													echo '<option value="' . $data['name'] . '" ';
													if ( !strcmp( $data['name'], $this->data['selectedVersion'] ) ) {
														echo 'selected';
													}
													echo '>' . $data['label'] . '</option>';
												} ?>
											</select>
										</div>
										<div class="productManual">
											<label for="docsManualSelect" class="navlabels">&nbsp;Manual:&nbsp;</label>
											<select id="docsManualSelect" name="selectedManual" onChange="changeManual();">
												<?php
												$navData = PonyDocsExtension::fetchNavDataForVersion( $this->data['selectedProduct'], $this->data['selectedVersion'] );
												print "<option value=''>Pick One...</option>";
												//loop through nav array and look for current URL
												foreach ( $navData as $manual ) {
													$selected = "";
													if ( !strcmp( $this->data['manualname'], $manual['longName'] ) ) {
														$selected = " selected ";
													}
													print "<option value='". $manual['firstUrl'] . "'   $selected>";
													print $manual['longName'];
													print "<!-- categories: {$manual['categories']} -->";
													print '</option>';
												} ?>
											</select>
										</div>
										<?php
										if ( sizeof($this->data['manualtoc'] ) ) { ?>
												&nbsp;<a href="<?php echo str_replace( '$1', '', $wgArticlePath );?>index.php?title=<?php echo $wgTitle->__toString();?>&action=pdfbook">PDF</a>
										<?php
										} ?>
				</div>
			</div>
		<?php $docselector = ob_get_clean(); ?>
		<?php ob_start(); ?>
			<?php
			if ( sizeof($this->data['manualtoc'] ) ) { ?>
				<?php
				$inUL = FALSE;
				$listid = "";
				foreach ( $this->data['manualtoc'] as $idx => $data ) {
					if ( 0 == $data['level'] ) {
						if ( $inUL ) {
							echo '</ul></div>';
							$inUL = FALSE;
						}
						$listid = "list" . $idx;
						echo '<div class="wikiSidebarBox collapsible">';
						echo '<h3>' . $data['text'] . '</h3>';
						echo '<ul>';
						$inUL = TRUE;
					} elseif ( 1 == $data['level'] ) {
						//if ( $data['current'] ) {
						//	echo '<li class="expanded">' . $data['text'] . '</li>';
						//} else {
							echo '<li><a href="' . wfUrlencode($data['link']) . '">' . $data['text'] . '</a></li>';
						//}
					} else {
						//if ( $data['current'] ) {
						//	echo '<li class="expanded" style="margin-left: 13px;">' . $data['text'] . '</li>';
						//} else {
							echo '<li style="margin-left: 13px;"><a href="' . wfUrlencode($data['link']) . '">' . $data['text'] . '</a></li>';
						//}
					}
				}
				if ( $inUL ) {
					echo '</ul></div>';
				}
			} ?>
		<?php $toc = ob_get_clean(); ?>
						<?php
					}
				}
			} ?>


		<div id="toc">
			<a href="http://www.brightpattern.com" title="Bright Pattern Home Page" rel="home">
				<img src="<?php $this->text( 'logopath' ) ?>" />
			</a>
			<div id="tocpad">
				<a href="<?php echo htmlspecialchars( $this->data['nav_urls']['mainpage']['href'] ) ?>" title="<?php $this->text( 'sitename' ); ?>" rel="home">
					<p>Documentation Home</p>
				</a>
				<form action="<?php $this->text( 'wgScript' ); ?>" id="searchform">
					<input type='hidden' name="title" value="<?php $this->text( 'searchtitle' ) ?>" />
					<div>
						<?php echo $this->makeSearchInput( array( 'type' => 'text', 'id' => 's' ) ); ?>
						<?php echo $this->makeSearchButton( 'go', array(
							'value' => "Go",
							'class' => "searchButton",
							'id'    => "searchsubmit",
						) ); ?>
					</div>
				</form>
				<?php echo $toc; ?>
			</div>
		</div>

		<div id="content">
		<div id="top-wrap" role="banner">
					<?php if ( $inDocumentation && $this->data && $this->data['manualname'] ) { ?>
						<div id="firstHeading" class="firstHeading"><?php echo $this->data['manualname']; ?></div>
						<?php
					} else { ?>
						<div  id="firstHeading" class="firstHeading"><a href="<?php echo htmlspecialchars( $this->data['nav_urls']['mainpage']['href'] ) ?>" title="<?php $this->text( 'sitename' ); ?>" rel="home"><?php $this->text( 'sitename' ); ?></a></div>
					<?php 
					} ?>
			<div id="tagline"><?php $this->msg( 'tagline' ) ?></div>
		</div>

		<?php echo $docselector; ?>

		<div id="main" role="main" >


			<div id="bodyContent">
					<?php if ( !$inDocumentation ) { ?>
						<h1 id="firstHeading" class="firstHeading"><?php $this->html( 'title' ); ?></h1>
						<?php
					} ?>
				<?php if ( $this->data['subtitle'] ) { ?>
					<div class="subtitle"><?php $this->html( 'subtitle' ) ?></div>
				<?php } ?>

				<?php $this->html( 'bodytext' ) ?>
				<?php $this->html( 'dataAfterContent' ); ?>
			</div>

			<div id="footer">
				<?php
					foreach ( $this->getFooterLinks() as $category => $links ) {
						if ( $category === 'info' ) {
							foreach ( $links as $key ) {
								echo '<p>';
								$this->html( $key );
								echo '</p>';
							}
						} else {
						}
					}
				?>
			</div>
		</div>
		</div>

		<?php $this->printTrail(); ?>

<!-- Google Tag Manager -->
<noscript><iframe src="//www.googletagmanager.com/ns.html?id=GTM-NVGLD4"
                  height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
        new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
        j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
        '//www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer','GTM-NVGLD4');</script>
<!-- End Google Tag Manager -->

		</body>
		</html>
		<?php
	}

	public function prepareDocumentation() {
		global $wgOut, $wgParser, $wgScriptPath, $wgTitle, $wgUser;
		/**
		 * We need a lot of stuff from our PonyDocs extension!
		 */
		$ponydocs = PonyDocsWiki::getInstance( $this->data['selectedProduct'] );
		$this->data['manuals'] = $ponydocs->getManualsForProduct( $this->data['selectedProduct'] );

		/**
		 * Adjust content actions as needed, such as add 'view all' link.
		 */
		$this->contentActions();
		$this->navURLS();

		/**
		 * Possible topic syntax we must handle:
		 * 
		 * Documentation:<topic> *Which may include a version tag at the end, we don't care about this.
		 * Documentation:<productShortName>:<manualShortName>:<topic>:<version>
		 * Documentation:<productShortName>:<manualShortName>
		 */

		/**
		 * Based on the name; i.e. 'Documentation:Product:Manual:Topic' we need to parse it out and store the manual name and
		 * the topic name as parameters. We store manual in 'manualname' and topic in 'topicname'. Special handling
		 * needs to be done for versions and TOC?
		 *
		 * 	0=NS (Documentation)
		 *  1=Product (Short name)
		 *  2=Manual (Short name)
		 *  3=Topic
		 *  4=Version
		 */
		$pManual = null;
		$pieces = explode( ':', $wgTitle->__toString() );
		$helpClass = '';

		/**
		 * This isn't a specific topic+version -- handle appropriately.
		 */
		if ( sizeof( $pieces ) < 4 ) {
			if ( !strcmp( PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . ':' . $this->data['selectedProduct']
				. PONYDOCS_PRODUCTVERSION_SUFFIX,
				$wgTitle->__toString() ) ) {
				$this->data['titletext'] = 'Versions Management - '.$this->data['selectedProduct'];
				$wgOut->addHTML( '<br><span class="' . $helpClass . '"><i>* Use {{#version:name|status}} to define a new version,'
					. ' where status is released, unreleased, or preview.'
					. ' Valid chars in version name are A-Z, 0-9, period, comma, and dash.</i></span>' );
				$wgOut->addHTML( '<br><span class="' . $helpClass . '"><i>* Use {{#versiongroup:name|message}} to set a banner'
					. ' message that will appear on every topic in every version following the versiongroup.</i></span>' );
			} elseif ( !strcmp( PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . ':' . $this->data['selectedProduct']
				. PONYDOCS_PRODUCTMANUAL_SUFFIX,
				$wgTitle->__toString() ) ) {
				$this->data['titletext'] = 'Manuals Management - '.$this->data['selectedProduct'];
				$wgOut->addHTML( '<br><span class="' . $helpClass . '"><i>'
					. '* Use {{#manual:manualShortName|displayName|categories}} to define a new manual.' );
				$wgOut->addHTML('<br><span class="' . $helpClass . '"><i>'
					. '* Prepend manual short name with ' . PONYDOCS_PRODUCT_STATIC_PREFIX. ' to define a static manual.'
					. '</i></span>');
				$wgOut->addHTML( '<br><span class="' . $helpClass . '">'
					. '<i>* If you omit display name, the short name will be used in links.</i></span>' );
				$wgOut->addHTML( '<br><span class="' . $helpClass . '">'
					. '<i>* Categories is a comma-separated list of categories</i></span>' );
			} elseif ( !strcmp( PONYDOCS_DOCUMENTATION_PRODUCTS_TITLE, $wgTitle->__toString() ) ) {
				$this->data['titletext'] = 'Products Management';
				$wgOut->addHTML( '<br><span class="' . $helpClass . '"><i>'
					. '* Use {{#product:productShortName|displayName|description|parent|categories}} to define a new product.'
					. '</i></span>' );
				$wgOut->addHTML( '<br><span class="' . $helpClass . '"><i>'
					. '* Prepend product short name with ' . PONYDOCS_PRODUCT_STATIC_PREFIX . ' to define a static product.'
					. '</i></span>' );
				$wgOut->addHTML( '<br><span class="' . $helpClass . '"><i>'
					. '* displayName, description, parent, and categories can be left empty.</i></span>' );
				$wgOut->addHTML( '<br><span class="' . $helpClass . '">'
					. '<i>* If you leave displayName empty, productShortName will be used in links.</i></span>' );
				$wgOut->addHTML( '<br><span class="' . $helpClass . '">'
					. '<i>* Categories is a comma-separated list of categories.</i></span>' );
				$wgOut->addHTML( '<br><span class="' . $helpClass . '">'
					. '<i>* Each product here <b>MUST</b> also be listed in $ponyDocsProductsList,'
					. ' usually configured in LocalSettings.php.</i></span>' );
			} elseif ( preg_match( '/(.*)TOC(.*)/', $pieces[2], $matches ) ) {
				$this->data['titletext'] = $matches[1] . ' Table of Contents Page';
				$wgOut->addHTML( '<br><span class="' . $helpClass . '"><i>'
					. '* Optionally start this page with {{#manualDescription:Manual Description.}}'
					. ' followed by two line-breaks to set a manual description for the Manual this TOC belongs to.'
					. '</i></span>' );
				$wgOut->addHTML( '<br><span class="' . $helpClass . '"><i>'
					. '* Topics are grouped into sections by section headers.'
					. ' Any line without markup is considered a section header.'
					. ' A section header is required before the the first topic tag.</i></span>');
				$wgOut->addHTML( '<br><span class="' . $helpClass . '"><i>'
					. '* Topic tags must be part of an unordered list.'
					. ' Use {{#topic:Display Name}} after a * (list item markup) to create topics.</i></span>' );
			} elseif ( sizeof( $pieces ) >= 2 && PonyDocsProductManual::IsManual( $pieces[1], $pieces[2] ) ) {
				$pManual = PonyDocsProductManual::GetManualByShortName( $pieces[1], $pieces[2] );
				if( $pManual ) {
					$this->data['manualname'] = $pManual->getLongName();
				} else {
					$this->data['manualname'] = $pieces[2];
				}
				$this->data['topicname'] = $pieces[3];
				$this->data['titletext'] = $pieces[2];
			} else {
				$this->data['topicname'] = $pieces[2];
			}
		} else {
			$pManual = PonyDocsProductManual::GetManualByShortName( $pieces[1], $pieces[2] );
			if ( $pManual ) {
				$this->data['manualname'] = $pManual->getLongName();
			} else {
				$this->data['manualname'] = $pieces[2];
			}
			$this->data['topicname'] = $pieces[3];

			$h1 = PonyDocsTopic::FindH1ForTitle( $wgTitle->__toString() );
			if ( $h1 !== FALSE ) {
				$this->data['titletext'] = $h1;
			}
		}

		/**
		 * Get current topic, passing it our global Article object.
		 * From this, generate our TOC based on the current topic selected.
		 * This generates our left sidebar TOC plus our prev/next/start navigation links.
		 * This should ONLY be done if we actually are WITHIN a manual, so special pages like TOC, etc. should not do this!
		 */

		if ( $pManual ) {
			$p = PonyDocsProduct::GetProductByShortName( $this->data['selectedProduct'] );
			$v = PonyDocsProductVersion::GetVersionByName( $this->data['selectedProduct'], $this->data['selectedVersion'] );
			$toc = new PonyDocsTOC( $pManual, $v, $p );
			list( $this->data['manualtoc'], $this->data['tocprev'], $this->data['tocnext'], $this->data['tocstart'] ) =
				$toc->loadContent();
			$this->data['toctitle'] = $toc->getTOCPageTitle();
		}

		/**
		 * Create a PonyDocsTopic from our article. From this we populate:
		 *
		 * topicversions:  List of version names topic is tagged with.
		 * inlinetoc:  Inline TOC shown above article body.
		 * catcode:  Special category code.
		 * cattext:  Category description.
		 * basetopicname:  Base topic name (w/o :<version> at end).
		 * basetopiclink:  Link to special TopicList page to view all same topics.
		 */

        $context = $this->skin->getContext();
 	 	$article = Article::newFromTitle($context->getTitle(), $context);
 	 	$topic = new PonyDocsTopic($article);

		if ( preg_match( '/^' . PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . ':(.*):(.*):(.*):(.*)/', $wgTitle->__toString() )
			|| preg_match( '/^' . PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . ':.*:.*TOC.*/', $wgTitle->__toString() ) ) {
			$this->data['topicversions'] = PonyDocsWiki::getVersionsForTopic( $topic );
			$this->data['inlinetoc'] = $topic->getSubContents();
			$this->data['versionclasses'] = $topic->getVersionClasses();
			$this->data['versionGroupMessage'] = $this->data['pVersion']->getVersionGroupMessage();

			/**
			 * Sort of a hack -- we only use this right now when loading a TOC page which is new/does not exist.
			 * When this happens a hook (AlternateEdit) adds an inline script to define this JS function,
			 * which populates the edit box with the proper Category tag based on the currently selected version.
			 */

			$this->data['body_onload'] = 'ponyDocsOnLoad();';

			switch( $this->data['catcode'] ) {
				case 0:
					$this->data['cattext'] = 'Applies to latest version which is currently unreleased.';
					break;
				case 1:
					$this->data['cattext'] = 'Applies to latest version.';
					break;
				case 2:
					$this->data['cattext'] = 'Applies to released version(s) but not the latest.';
					break;
				case 3:
					$this->data['cattext'] = 'Applies to latest preview version.';
					break;
				case 4:
					$this->data['cattext'] = 'Applies to one or more preview version(s) only.';
					break;
				case 5:	
					$this->data['cattext'] = 'Applies to one or more unreleased version(s) only.';
					break;
				case -2: /** Means its not a a title name which should be checked. */
					break;
				default:
					$this->data['cattext'] = 'Does not apply to any version of PonyDocs.';
					break;
			}
		}

		$this->data['basetopicname'] = $topic->getBaseTopicName();
		if ( strlen( $this->data['basetopicname'] ) ) {
			$this->data['basetopiclink'] = '<a href="' . $wgScriptPath . '/index.php?title=Special:TopicList&topic='
				. $this->data['basetopicname'] . '">View All</a>';
		}
		$temp = PonyDocsTopic::FindH1ForTitle( PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . ':' . $topic->getTitle()->getText() );
		if ( $temp !== false ) {
			// We got an H1!
			$this->data['pagetitle'] = $temp;
		}
	}

	private function contentActions() {
		global $ponyDocsEmployeeGroup, $wgArticlePath, $wgScriptPath, $wgTitle, $wgUser;

		$groups = $wgUser->getGroups();
		$authProductGroup = PonyDocsExtension::getDerivedGroup();

		if ( preg_match( '/' . PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . ':(.*):(.*):(.*):(.*)/i', $wgTitle->__toString(), $match ) ) {
			if ( in_array( $ponyDocsEmployeeGroup, $groups ) || in_array( $authProductGroup, $groups ) ) {
				array_pop( $match );  array_shift( $match );
				$title = PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . ':' . implode( ':', $match );

				$this->data['content_actions']['viewall'] = array(
					'class' => '',
					'text' => 'View All',
					'href' => $wgScriptPath . '/index.php?title=Special:TopicList&topic=' . $title 
				);
			}
			if ( $wgUser->isAllowed( 'branchtopic' ) ) {
				$this->data['content_actions']['branch'] = array(
					'class' => '',
					'text'  => 'Branch',
					'href'	=> $wgScriptPath . '/Special:BranchInherit?titleName=' . $wgTitle->__toString()
				);
			}
		} elseif ( preg_match( '/' . PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . ':(.*):(.*)TOC(.*)/i', $wgTitle->__toString(), $match ) ) {
			if ( $wgUser->isAllowed( 'branchmanual' ) ) {
				$this->data['content_actions']['branch'] = array(
					'class' => '',
					'text'  => 'Branch',
					'href'	=> $wgScriptPath . '/Special:BranchInherit?toc=' . $wgTitle->__toString()
				);
			}
		}
	}

	/**
	 * Update the nav URLs (toolbox) to include certain special pages for authors and bureaucrats.
	 */
	private function navURLS() {
		global $wgArticlePath, $wgTitle, $wgUser;

		$groups = $wgUser->getGroups();
		$authProductGroup = PonyDocsExtension::getDerivedGroup();

		if ( in_array( 'bureaucrat', $groups ) || in_array( $authProductGroup, $groups ) ) {
			$this->data['nav_urls']['special_doctopics'] = array(
				'href' => str_replace( '$1', 'Special:DocTopics', $wgArticlePath ),
				'text' => 'Document Topics' );

			$this->data['nav_urls']['special_tocmgmt'] = array(
				'href' => str_replace( '$1', 'Special:TOCList', $wgArticlePath ),
				'text' => 'TOC Management' );

			$this->data['nav_urls']['documentation_manuals'] = array(
				'href' => str_replace( '$1', PONYDOCS_DOCUMENTATION_NAMESPACE_NAME . ':Manuals', $wgArticlePath ),
				'text' => 'Manuals' );
			
			$partialUrl = SpecialDocumentLinks::getDocumentLinksArticle();

			$this->data['nav_urls']['document_links'] = array(
				'href' => str_replace( '$1', 'Special:SpecialDocumentLinks?t=' . $wgTitle->getNsText() . ':'
					. $partialUrl, $wgArticlePath),
				'text' => 'Document Links');
		}
	}

	/**
	 * Output select options respecting a single-level parent/child product hierarchy
	 * 
	 * TODO: Handle multiple levels of parent/child relationships
	 * 
	 * @param string $parent  Short name of parent whose children we want to output
	 */
	private function hierarchicalProductSelect( $parent = NULL ) {
		foreach ( $this->data['products'] as $data ) {
			// We're at the top-level, output all top-level Products
			if ( $parent === NULL && $data['parent'] == '' ) {
				$selected = !strcmp( $data['name'], $this->data['selectedProduct'] ) ? 'selected="selected"' : '';
				echo '<option value="' . $data['name'] . '" ' . $selected . '>';
				echo $data['label'];
				echo "<!-- categories: " . implode(',', $data['categories']) . "-->";
				echo "</option>\n";
				echo $this->hierarchicalProductSelect( $data['name'] );
			} elseif ( $parent !== NULL && $data['parent'] == $parent ) {
				$selected = !strcmp( $data['name'], $this->data['selectedProduct'] ) ? 'selected="selected"' : '';
				echo '<option class="child" value="' . $data['name'] . '" ' . $selected . '>';
				echo '-- ' . $data['label'];
				echo "</option>\n";
			}
		}
	}

}
