import copy
import os
import sys
import unittest
sys.path.insert(0,'worker')
from gateway import ModelGateway,validate_response

class GatewayTests(unittest.TestCase):
    def setUp(self):self.data={'claims':[{'type':'business_rule','title':'Possível regra','description':'Pendente de confirmação','classification':'inferred','confidence':.7,'review_status':'approved','evidence':[{'id':'f1'}]}]}
    def test_provider_cannot_approve(self):self.assertEqual(validate_response(self.data,{'f1'})['claims'][0]['review_status'],'pending')
    def test_invalid_evidence(self):
        with self.assertRaises(ValueError):validate_response(self.data,{'other'})
    def test_nan_rejected(self):
        self.data['claims'][0]['confidence']=float('nan')
        with self.assertRaises(ValueError):validate_response(self.data,{'f1'})
    def test_missing_classification(self):
        del self.data['claims'][0]['classification']
        with self.assertRaises(ValueError):validate_response(self.data,{'f1'})
    def test_disabled_makes_no_request(self):
        os.environ['ALLOW_EXTERNAL_MODELS']='0'
        with self.assertRaises(RuntimeError):ModelGateway().analyze({},set())

if __name__=='__main__':unittest.main()
