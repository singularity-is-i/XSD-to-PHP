<?php
namespace com\mikebevz\xsd2php;

/**
 * Copyright 2010 Mike Bevz <myb@mikebevz.com>
 * 
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 * 
 *   http://www.apache.org/licenses/LICENSE-2.0
 * 
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

require_once dirname(__FILE__).'/PHPClass.php';
require_once dirname(__FILE__).'/Common.php';
 
 /** 
 * Generate PHP classes based on XSD schema
 * 
 * @author Mike Bevz <myb@mikebevz.com>
 * @version 0.0.1
 * 
 */
class Xsd2Php extends Common
{
    /**
     * XSD schema to convert from
     * @var String
     */
    private $xsdFile;
    
    /**
     * 
     * @var DOMDocument
     */
    private $dom;
    
    /**
     * 
     * @var DOMXPath
     */
    private $xpath;
    
    /**
     * Namespaces in the current xsd schema
     * @var array
     */
    private $nspace;
    
    /**
     * XML file suitable for PHP code generation
     * @var string
     */
    private $xmlForPhp;
    
    /**
     * Show debug info
     * @var boolean
     */
    public $debug = false;
    
    /**
     * Namespaces = array (className => namespace ), used in dirs/files generation 
     * @var array
     */
    private $namespaces;
    
    private $shortNamespaces;
    
    private $xmlSource;
    
    /**
     * XSD root namespace alias (fx, xsd = http://www.w3.org/2001/XMLSchema)
     * @var string
     */
    private $xsdNs;
    
    /**
     * Already processed imports
     * @var array
     */
    private $loadedImportFiles = array();
    
    
    /**
     * XSD schema converted to XML
     * @return string $xmlSource
     */
    public function getXmlSource()
    {
        return $this->xmlSource;
    }

	/**
	 * 
     * @param string $xmlSource XML Source
     */
    public function setXmlSource($xmlSource)
    {
        $this->xmlSource = $xmlSource;
    }
    
    /**
     * 
     * @param string $xsdFile Xsd file to convert
     * 
     * @return void
     */
	public function __construct($xsdFile, $debug = false)
    {
        if ($debug != false) {
            $this->debug = $debug;
        }
        
        $this->xsdFile = $xsdFile;
        
        $this->dom = new \DOMDocument();
        $this->dom->load($this->xsdFile, 
                         LIBXML_DTDLOAD | 
                         LIBXML_DTDATTR |
                         LIBXML_NOENT |
                         LIBXML_XINCLUDE);
                 
        $this->xpath = new \DOMXPath($this->dom);         
        
        $this->shortNamespaces = $this->getNamespaces($this->xpath);
        
        //$this->dom = $this->importIncludes($this->xsdFile, $this->dom);
        $this->dom = $xsd = $this->loadIncludes($this->dom, dirname($this->xsdFile));
        $this->dom = $this->loadImports($this->dom, $this->xsdFile);
        
        
       // $this->shortNamespaces = $this->getNamespaces($this->xpath);
        if ($this->debug) print_r($this->shortNamespaces);
        
    }
    
    /**
     * Return array of namespaces of the docuemtn
     * @param DOMXPath $xpath
     * 
     * @return array
     */
    public function getNamespaces($xpath) {
        $query   = "//namespace::*";
        $entries =  $xpath->query($query);
        $nspaces = array();
        
        foreach ($entries as $entry) {
            if ($entry->nodeValue == "http://www.w3.org/2001/XMLSchema") {
                $this->xsdNs = preg_replace('/xmlns:(.*)/', "$1", $entry->nodeName);
            }
            if (//$entry->nodeName != $this->xsdNs 
                //&& 
                $entry->nodeName != 'xmlns:xml')  {
                    if (preg_match('/:/', $entry->nodeName)) {
                        $nodeName = explode(':', $entry->nodeName); 
                        $nspaces[$nodeName[1]] = $entry->nodeValue;
                    
                    } else {
                        $nspaces[$entry->nodeName] = $entry->nodeValue;
                    }
            }

        } 
        return $nspaces;
    }
    
    /**
     * Save generated classes to directory
     * @param string  $dir             Directory to save classes to
     * @param boolean $createDirectory Create directory, false by default
     * 
     * @return void
     */
    public function saveClasses($dir, $createDirectory) {
        $this->setXmlSource($this->getXML()->saveXML());
        $this->savePhpFiles($dir, $createDirectory);
    }
    
    private $importHeadNS = array();
    
    /**
     * Recursive method adding imports and includes
     * 
     * @param string      $xsdFile Path to XSD Schema filename
     * @param DOMDocument $domNode 
     * @param DOMDocument $domRef 
     */
    /*
    private function loadImportsOld($xsdFile, $domNode, $domRef = null) {
        
        if (is_null($domRef)) {
            $domRef = $domNode;
        }
        
        
        $xpath = new \DOMXPath($domNode);
        
        
        $query = "//*[local-name()='import' and namespace-uri()='http://www.w3.org/2001/XMLSchema']";
        $entries = $xpath->query($query);
            foreach ($entries as $entry) {
                $namespace = $entry->getAttribute('namespace');
                if ($this->debug) print($namespace."\n");
                
                $parent = $entry->parentNode;
                $xsd = new \DOMDocument();
                
                $xsdFileName = realpath(dirname($xsdFile) . DIRECTORY_SEPARATOR . $entry->getAttribute("schemaLocation"));
                if (!file_exists($xsdFileName)) {
                    if ($this->debug) print_r('File '.$xsdFileName. "does not exist"."\n");
                    continue;
                }
                
                if ($this->debug) print_r("Importing ".$xsdFileName."\n");
                
                if (in_array($xsdFileName, $this->loadedImportFiles)) {
                    if ($this->debug) print("File ".$xsdFileName." has been already imported");
                    continue;
                }
                
                $result = $xsd->load($xsdFileName, 
                            LIBXML_DTDLOAD|LIBXML_DTDATTR|LIBXML_NOENT|LIBXML_XINCLUDE);
                $this->loadedImportFiles[] = $xsdFileName;
                $this->loadedImportFiles = array_unique($this->loadedImportFiles);
                
                
                
                $mxpath = new \DOMXPath($xsd);
                $this->shortNamespaces = array_merge($this->shortNamespaces, $this->getNamespaces($mxpath));
                    
                
                if ($result) {
                        //$xsd = $this->importIncludes($xsdFileName, $xsd);
                        foreach ($xsd->documentElement->childNodes as $node) {
                            if ($node->nodeName == "xsd:import") {
                                // Do not change Namespace for import and include tags
                                if ($this->debug) print($node->nodeName." \n". $node->getAttribute('namespace'). "\n");
                                
                                $newNode = $domNode->importNode($node, true);
                                $parent->insertBefore($newNode, $entry);
                                
                                continue;
                            }
                            
                            $newNodeNs = $domNode->createAttribute("namespace");
                            
                            $textEl = $domNode->createTextNode($namespace);
                            $newNodeNs->appendChild($textEl);

                            $newNode = $domNode->importNode($node, true);
                            $newNode->appendChild($newNodeNs); 
                            
                            $parent->insertBefore($newNode, $entry);
                            //if ($this->debug) print_r($parent->nodeName);
                        }
                        $parent->removeChild($entry);
                  
                } else {
                    if ($this->debug) print 'FIle '. $xsdFileName. " was not loaded";
                }
                
                $xpath = new \DOMXPath($xsd);
                $query = "//*[local-name()='import' and namespace-uri()='http://www.w3.org/2001/XMLSchema']";
                $imports = $xpath->query($query);
                if ($imports->length != 0) {
                   $domRef = $this->loadImports($xsdFileName, $xsd, $domRef);
                } 
                
            }
            
            if ($this->debug) print_r($domRef->saveXml());
            if ($this->debug) print_r("------------------------------------\n");
            
            return $domRef;
    }*/
    
    /**
     * 
     * @param DOMDocument $dom     DOM model of the schema 
     * @param string      $xsdFile Full path to first XSD Schema
     */
    public function loadImports($dom, $xsdFile = '') {
        
        $xpath = new \DOMXPath($dom);
        $query = "//*[local-name()='import' and namespace-uri()='http://www.w3.org/2001/XMLSchema']";
        $entries = $xpath->query($query);
        if ($entries->length == 0) {
            return $dom;
        }
        foreach ($entries as $entry) {
            // load XSD file
            $namespace = $entry->getAttribute('namespace');
            $parent = $entry->parentNode;
            $xsd = new \DOMDocument();
            $xsdFileName = realpath(dirname($xsdFile).DIRECTORY_SEPARATOR.$entry->getAttribute("schemaLocation"));
            if ($this->debug) print('Importing '.$xsdFileName."\n");
            
            if (!file_exists($xsdFileName)) {
               if ($this->debug) print $xsdFileName. " is not found \n"; 
               continue; 
            }
            if (in_array($xsdFileName, $this->loadedImportFiles)) {
                if ($this->debug) print("Schema ".$xsdFileName." has been already imported");
                $parent->removeChild($entry);  
                continue;
            }
            $filepath = dirname($xsdFileName);
            $result = $xsd->load($xsdFileName, 
                            LIBXML_DTDLOAD|LIBXML_DTDATTR|LIBXML_NOENT|LIBXML_XINCLUDE);
            if ($result) {
                $mxpath = new \DOMXPath($xsd);
                $this->shortNamespaces = array_merge($this->shortNamespaces, $this->getNamespaces($mxpath));
                
                //@todo Load includes recursively
                $xsd = $this->loadIncludes($xsd, $filepath);
                
                $this->loadedImportFiles[] = $xsdFileName;
                $this->loadedImportFiles = array_unique($this->loadedImportFiles);
            }  
            foreach ($xsd->documentElement->childNodes as $node) {
                
                if ($node->nodeName == $this->xsdNs.":import") {
                    // Do not change Namespace for import and include tags
                    //if ($this->debug) print("Insert Import ".$node->nodeName." NS=". $node->getAttribute('namespace'). "\n");

                    $loc = realpath($filepath.DIRECTORY_SEPARATOR.$node->getAttribute('schemaLocation'));
                    $node->setAttribute('schemaLocation', $loc);
                    if ($this->debug) print('Change imported schema location to '.$loc." \n");
                    $newNode = $dom->importNode($node, true);
                    $parent->insertBefore($newNode, $entry);
                                
                    continue;
                } else {
                    //if ($this->debug) print($node->nodeName." \n". $namespace. "\n");
                    $newNodeNs = $xsd->createAttribute("namespace");
                    $textEl = $xsd->createTextNode($namespace);
                    $newNodeNs->appendChild($textEl);
                    $node->appendChild($newNodeNs);
                    
                    $newNode = $dom->importNode($node, true);
                    $parent->insertBefore($newNode, $entry);
                }        
            }
            // add to $dom
            $parent->removeChild($entry);  
        }
        
        $xpath = new \DOMXPath($dom);
        $query = "//*[local-name()='import' and namespace-uri()='http://www.w3.org/2001/XMLSchema']";
        $imports = $xpath->query($query);
        if ($imports->length != 0) {
            $dom = $this->loadImports($dom);
        } 
        
        if ($this->debug) print_r("\n------------------------------------\n");
        return $dom;
    }
    
    public function loadIncludes($dom, $filepath = '') {
        $xpath = new \DOMXPath($dom);    
        $query = "//*[local-name()='include' and namespace-uri()='http://www.w3.org/2001/XMLSchema']";
        $includes = $xpath->query($query);
        
        foreach ($includes as $entry) {
            $parent = $entry->parentNode;
            $xsd = new \DOMDocument();
            $xsdFileName = realpath($filepath.DIRECTORY_SEPARATOR.$entry->getAttribute("schemaLocation"));
            if ($this->debug) print('Including '.$xsdFileName."\n");
            
            if (!file_exists($xsdFileName)) {
               if ($this->debug) print $xsdFileName. " is not found \n"; 
               continue; 
            }
            
            $result = $xsd->load($xsdFileName, 
                            LIBXML_DTDLOAD|LIBXML_DTDATTR|LIBXML_NOENT|LIBXML_XINCLUDE);
            if ($result) {
                $mxpath = new \DOMXPath($xsd);
                $this->shortNamespaces = array_merge($this->shortNamespaces, $this->getNamespaces($mxpath));
                
            } 
            foreach ($xsd->documentElement->childNodes as $node) {
                if ($node->nodeName == $this->xsdNs.":include") {
                    $loc = realpath($filepath.DIRECTORY_SEPARATOR.$node->getAttribute('schemaLocation'));
                    $node->setAttribute('schemaLocation', $loc);
                    if ($this->debug) print('Change included schema location to '.$loc." \n");
                    $newNode = $dom->importNode($node, true);
                    $parent->insertBefore($newNode, $entry);
                } else {
                    $newNode = $dom->importNode($node, true);
                    $parent->insertBefore($newNode, $entry);
                }
            }
            $parent->removeChild($entry);
        }
        
        $xpath = new \DOMXPath($dom);
        $query = "//*[local-name()='include' and namespace-uri()='http://www.w3.org/2001/XMLSchema']";
        $includes = $xpath->query($query);
        if ($includes->length != 0) {
            $dom = $this->loadIncludes($dom);
        } 
        
        if ($this->debug) print_r("\n------------------------------------\n");
        
        return $dom;
    }
    
    /**
     * Recursive import of includes
     * 
     * @param string      $xsdFile Path to XSD file
     * @param DOMDocument $domNode DOM document
     * @param DOMDocument $domRef  Used only recursivelly
     */
    /*
    private function importIncludesOld($xsdFile, $domNode, $domRef = null) {
        
        if (is_null($domRef)) {
            $domRef = $domNode;
        }

        $xpath = new \DOMXPath($domNode);    
        $query = "//*[local-name()='include' and namespace-uri()='http://www.w3.org/2001/XMLSchema']";
        $includes = $xpath->query($query);
        
        foreach ($includes as $include) {
            $parent = $include->parentNode;
            //$namespace = $include->parentNode;
            $xsd = new \DOMDocument();
            
            $xsdFileName = realpath(dirname($xsdFile) . "/" . $include->getAttribute("schemaLocation"));
            if ($this->debug) print_r('Including schema '.$xsdFileName."\n");
            if (!file_exists($xsdFileName)) {
                if ($this->debug) print_r('Include File '.$xsdFileName. "does not exist"."\n");
                continue;
            }
            $result = $xsd->load($xsdFileName, 
                                LIBXML_DTDLOAD|LIBXML_DTDATTR|LIBXML_NOENT|LIBXML_XINCLUDE);
                               
            if ($result) {
                foreach ($xsd->documentElement->childNodes as $node) {
                    $newNode = $domNode->importNode($node, true);
                    $parent->insertBefore($newNode, $include);
                }
                $parent->removeChild($include);
            } 

            $xpath = new \DOMXPath($xsd);
            $query = "//*[local-name()='import' and namespace-uri()='http://www.w3.org/2001/XMLSchema']";
            $imports = $xpath->query($query);
                    
            if ($imports->length != 0) {
               $domRef = $this->importIncludes($xsdFileName, $xsd, $domRef);
            }
            
        }
        if ($this->debug) print("Processed schema $xsdFile\n");
        return $domRef;
    }
    */
    
    /**
     * Convert XSD to XML suitable for PHP code generation
     * 
     * @return string
     */
    public function getXmlForPhp()
    {
        return $this->xmlForPhp;
    }

	/**
     * @param string $xmlForPhp XML
     * 
     * @return void
     */
    public function setXmlForPhp($xmlForPhp)
    {
        $this->xmlForPhp = $xmlForPhp;
    }
    
    /**
     * Convert XSD to XML suitable for further processing
     * 
     * @return string XML string
     */
	public function getXML()
    {
        //if ($this->getXmlSource() != '') {
            try {
                $xsl    = new \XSLTProcessor();
                $xslDom = new \DOMDocument();
                $xslDom->load(dirname(__FILE__) . "/xsd2php2.xsl");
                $xsl->registerPHPFunctions();
                $xsl->importStyleSheet($xslDom);
                $dom = $xsl->transformToDoc($this->dom);
                $dom->formatOutput = true;
    
                return $dom;
                
            } catch (\Exception $e) {
                throw new \Exception(
                    "Error interpreting XSD document (".$e->getMessage().")");
            }
        //} else {
        //    return $this->dom;
        //}
        
        
    }
    
    /**
     * Save PHP files to directory structure 
     * 
     * @param string $dir Directory to save files to
     * 
     * @return void
     * 
     * @throws RuntimeException if given directory does not exist
     */
    private function savePhpFiles($dir, $createDirectory = false) {
        if (!file_exists($dir) && $createDirectory === false) {
            throw new \RuntimeException($dir." does not exist");
        }
        
        if (!file_exists($dir) && $createDirectory === true) {
            mkdir($dir, 0777, true);
        }
        
        $classes = $this->getPHP();
        
        foreach ($classes as $fullkey => $value) {
            $keys = explode("|", $fullkey);
            $key = $keys[0];
            $namespace = $this->namespaceToPath($keys[1]); 
            $targetDir = $dir.DIRECTORY_SEPARATOR.$namespace;
            if (!file_exists($targetDir)) {
                mkdir($targetDir, 0777, true);
            }
            file_put_contents($targetDir.DIRECTORY_SEPARATOR.$key.'.php', $value);
        }
        if ($this->debug) echo "Generated classes saved to ".$dir;
    }
    
    /**
     * Return generated PHP source code
     * 
     * @return string
     */
    private function getPHP() {
        $phpfile = $this->getXmlForPhp();
        if ($phpfile == '' && $this->getXmlSource() == '') {
            throw new \RuntimeException('There is no XML generated');
        }
        
        $dom = new \DOMDocument();
        //print_r($this->getXmlSource());
        if ($this->getXmlSource() != '') {
            $dom->loadXML($this->getXmlSource(), LIBXML_DTDLOAD | LIBXML_DTDATTR |
                 LIBXML_NOENT | LIBXML_XINCLUDE);
        } else {
            $dom->load($phpfile, LIBXML_DTDLOAD | LIBXML_DTDATTR |
                 LIBXML_NOENT | LIBXML_XINCLUDE);
        }
                 
        $xPath = new \DOMXPath($dom);         
                 
        $classes = $xPath->query('//classes/class');
        
        $sourceCode = array();
        foreach ($classes as $class) {
            
            $phpClass = new PHPClass();
            $phpClass->name = $class->getAttribute('name');
            
            if ($class->getAttribute('type') != '') {
                $phpClass->type = $class->getAttribute('type');
            }
            
            if ($class->getAttribute('simpleType') != '') {
                $phpClass->type = $class->getAttribute('simpleType');
            }
            if ($class->getAttribute('namespace') != '') {
                $phpClass->namespace = $class->getAttribute('namespace');
            }
            
            if ($class->getElementsByTagName('extends')->length > 0) {
                if (!in_array($class->getElementsByTagName('extends')->item(0)->getAttribute('name'), $this->basicTypes)) {
                    $phpClass->extends = $class->getElementsByTagName('extends')->item(0)->getAttribute('name');
                    $phpClass->type    = $class->getElementsByTagName('extends')->item(0)->getAttribute('name');
                    $phpClass->extendsNamespace = $this->namespaceToPhp($class->getElementsByTagName('extends')->item(0)->getAttribute('namespace'));
                }
            }
            
            $docs = $xPath->query('docs/doc', $class);
            $docBlock = array();
            $docBlock['xmlNamespace'] = $this->expandNS($phpClass->namespace);
            $docBlock['xmlType']      = $phpClass->type;
            $docBlock['xmlName']      = $phpClass->name;
            
            foreach ($docs as $doc) {
                if ($doc->nodeValue != '') {
                    $docBlock["xml".$doc->getAttribute('name')] = $doc->nodeValue;
                } elseif ($doc->getAttribute('value') != '') {
                    $docBlock["xml".$doc->getAttribute('name')] = $doc->getAttribute('value');
                }    
            }
            
            $phpClass->classDocBlock = $docBlock;
            
            $props      = $xPath->query('property', $class);
            $properties = array();
            $i = 0;
            foreach($props as $prop) {
                $properties[$i]['name'] = $prop->getAttribute('name');
                $docs                   = $xPath->query('docs/doc', $prop);
                foreach ($docs as $doc) {
                    $properties[$i]["docs"][$doc->getAttribute('name')] = $doc->nodeValue;
                } 
                if ($prop->getAttribute('xmlType') != '') {
                    $properties[$i]["docs"]['xmlType']      = $prop->getAttribute('xmlType');
                }
                if ($prop->getAttribute('namespace') != '') {
                    $properties[$i]["docs"]['xmlNamespace'] = $this->expandNS($prop->getAttribute('namespace'));
                }
                if ($prop->getAttribute('minOccurs') != '') {
                    $properties[$i]["docs"]['xmlMinOccurs'] = $prop->getAttribute('minOccurs');
                }
                if ($prop->getAttribute('maxOccurs') != '') {
                    $properties[$i]["docs"]['xmlMaxOccurs'] = $prop->getAttribute('maxOccurs');
                }
                if ($prop->getAttribute('name') != '') {
                    $properties[$i]["docs"]['xmlName']      = $prop->getAttribute('name');
                }
                
                //@todo if $prop->getAttribute('maxOccurs') > 1 - var can be an array
                if ($prop->getAttribute('type') != '') {
                    $properties[$i]["docs"]['var']          = $prop->getAttribute('type');
                }
                $i++;
            }
            
            $phpClass->classProperties = $properties;
            $namespaceClause = '';
            if ($docBlock['xmlNamespace'] != '') {
                $namespaceClause           = "namespace ".$this->namespaceToPhp($docBlock['xmlNamespace']).";\n";
            } 
            $sourceCode[$docBlock['xmlName']."|".$phpClass->namespace] = "<?php\n".
                $namespaceClause.
                $phpClass->getPhpCode();
        }         
        return $sourceCode; 
     }

    /**
     * Resolve short namespace 
     * @param string $ns Short namespace 
     * 
     * @return string
     */    
    private function expandNS($ns) {
        foreach($this->shortNamespaces as $shortNs => $longNs) {
           if ($ns == $shortNs) {
               $ns = $longNs;
           }
        }
        return $ns;  
    }
     
     /**
      * Convert XML URI to PHP complient namespace
      * 
      * @param string $xmlNS XML URI
      * 
      * @return string
      */
     private function namespaceToPhp($xmlNS) {
         $ns = $xmlNS;
         $ns = $this->expandNS($ns);
         if (preg_match('/urn:/',$ns)) {
            //@todo check if there are any components of namespace which are
            $ns = preg_replace('/-/', '_',$ns);
            $ns = preg_replace('/urn:/', '', $ns);
            $ns = preg_replace('/:/','\\', $ns);
         }
         
         $ns = explode('\\', $ns);
         $i = 0;
         foreach($ns as $elem) {
            if (preg_match('/^[0-9]+$/', $elem)) {
                $ns[$i] = "_".$elem;
            }
            
            if (in_array($elem, $this->reservedWords)) {
                $ns[$i] = "_".$elem;
            } 
            $i++;
         }
        
         $ns = implode('\\', $ns);
         
         return $ns; 
     }
     
     /**
      * Convert XML URI to Path
      * @param string $xmlNS XML URI
      * 
      * @return string
      */
     private function namespaceToPath($xmlNS) {
        $ns = $xmlNS;
        $ns = $this->expandNS($ns);
        
        if (preg_match('/urn:/', $ns)) {
            $ns = preg_replace('/-/', '_', $ns);
            $ns = preg_replace('/urn:/', '', $ns);
            $ns = preg_replace('/:/', DIRECTORY_SEPARATOR, $ns);
        }
        $ns = explode(DIRECTORY_SEPARATOR, $ns);
        $i = 0;
        foreach($ns as $elem) {
            if (preg_match('/^[0-9]$/', $elem)) {
                $ns[$i] = "_".$elem;
            }
            if (in_array($elem, $this->reservedWords)) {
                $ns[$i] = "_".$elem;
            } 
            $i++;
        }
        $ns = implode(DIRECTORY_SEPARATOR, $ns);
        return $ns;
     }
}