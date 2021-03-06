//
//  InfectedKeysFormData.swift
//  TracePrivately
//

#if canImport(UIKit)
import UIKit
#else
import Foundation
#endif

protocol InfectedKeysFormDataField {
    var name: String { get }
    var requestJson: [String: Any]? { get }
    
    var isValid: Bool { get }
}

struct InfectedKeysFormDataStringField: InfectedKeysFormDataField {
    let name: String
    let value: String
    
    var requestJson: [String : Any]? {
        return [
            "name": self.name,
            "type": "string",
            "str": self.value
        ]
    }
    
    var isValid: Bool {
        return true
    }
}

struct InfectedKeysFormDataDateField: InfectedKeysFormDataField {
    let name: String
    
    let date: Date
    let timeZone: TimeZone
    
    var requestJson: [String : Any]? {
        
        var calendar = Calendar(identifier: .gregorian)
        calendar.timeZone = timeZone
        let dc = calendar.dateComponents([ .year, .month, .day ], from: date)
        
        guard let y = dc.year, let m = dc.month, let d = dc.day else {
            return nil
        }
        
        return [
            "name": self.name,
            "type": "date",
            "y": y,
            "m": m,
            "d": d
        ]
    }
    
    var isValid: Bool {
        return true
    }
}

#if !os(macOS)
// TODO: Resize image to an adequate size
struct InfectedKeysFormDataImageField: InfectedKeysFormDataField {
    let name: String
    let image: UIImage
    
    var requestJson: [String : Any]? {
        guard let pngData = self.image.pngData() else {
            return nil
        }
        
        return [
            "name": self.name,
            "type": "image/png",
            "str": pngData.base64EncodedString(),
        ]
    }
    
    var isValid: Bool {
        return true
    }
}
#endif

struct InfectedKeysFormData {
    
    let fields: [InfectedKeysFormDataField]
    
    /// Returns an array of data that can be encoded to JSON
    var requestJson: [[String: Any]] {
        return self.fields.compactMap { $0.requestJson }
    }
}
