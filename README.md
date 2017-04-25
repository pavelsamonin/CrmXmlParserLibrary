"# CrmXmlParserLibrary" 
Created for my project on Codeigniter, but can be used for other frameworks.
Simply load library and use it like this (for Codeigniter):

$this->load->library('crmxmlparserlibrary');

$this->crmxmlparserlibrary->parseIt($entities);

$entities - is a result of fetchxml response, like $responsedom->getElementsbyTagName("Entity")
