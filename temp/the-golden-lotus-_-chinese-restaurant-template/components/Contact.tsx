import React, { useState } from 'react';
import { MapPin, Phone, Mail, Clock, Instagram, Facebook, Twitter } from 'lucide-react';

const Contact: React.FC = () => {
  const [formStatus, setFormStatus] = useState<'idle' | 'submitting' | 'success'>('idle');

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setFormStatus('submitting');
    // Simulate API call
    setTimeout(() => {
      setFormStatus('success');
      setTimeout(() => setFormStatus('idle'), 3000);
    }, 1500);
  };

  const handleSocialClick = (e: React.MouseEvent<HTMLAnchorElement>) => {
    e.preventDefault();
    // In a real app, these would open in a new tab
  };

  return (
    <section id="contact" className="bg-white">
      {/* Map / Info Section */}
      <div className="flex flex-col lg:flex-row">
        {/* Info */}
        <div className="lg:w-1/2 bg-foreground text-white p-12 lg:p-20 flex flex-col justify-center">
          <h2 className="text-4xl font-serif font-bold text-secondary mb-8">Visit Us</h2>
          
          <div className="space-y-8">
            <div className="flex items-start gap-4">
              <MapPin className="w-6 h-6 text-primary mt-1" />
              <div>
                <h3 className="font-bold uppercase tracking-widest mb-1">Location</h3>
                <p className="text-gray-400">123 Silk Road Avenue,<br />Chinatown District, NY 10013</p>
              </div>
            </div>

            <div className="flex items-start gap-4">
              <Clock className="w-6 h-6 text-primary mt-1" />
              <div>
                <h3 className="font-bold uppercase tracking-widest mb-1">Hours</h3>
                <p className="text-gray-400">Mon - Thu: 11:00 AM - 10:00 PM</p>
                <p className="text-gray-400">Fri - Sun: 11:00 AM - 11:00 PM</p>
              </div>
            </div>

            <div className="flex items-start gap-4">
              <Phone className="w-6 h-6 text-primary mt-1" />
              <div>
                <h3 className="font-bold uppercase tracking-widest mb-1">Contact</h3>
                <p className="text-gray-400">+1 (555) 123-4567</p>
                <p className="text-gray-400">reservations@goldenlotus.com</p>
              </div>
            </div>
          </div>

          <div className="mt-12 flex gap-4">
             <a href="#" onClick={handleSocialClick} className="p-2 bg-gray-800 rounded-full hover:bg-primary transition-colors"><Instagram className="w-5 h-5" /></a>
             <a href="#" onClick={handleSocialClick} className="p-2 bg-gray-800 rounded-full hover:bg-primary transition-colors"><Facebook className="w-5 h-5" /></a>
             <a href="#" onClick={handleSocialClick} className="p-2 bg-gray-800 rounded-full hover:bg-primary transition-colors"><Twitter className="w-5 h-5" /></a>
          </div>
        </div>

        {/* Form */}
        <div className="lg:w-1/2 p-12 lg:p-20 bg-background">
          <h2 className="text-4xl font-serif font-bold text-foreground mb-8">Reservations</h2>
          <form onSubmit={handleSubmit} className="space-y-6">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <label className="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-2">Name</label>
                <input type="text" required className="w-full bg-white border border-gray-300 p-3 focus:border-primary focus:outline-none transition-colors" placeholder="John Doe" />
              </div>
              <div>
                <label className="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-2">Phone</label>
                <input type="tel" required className="w-full bg-white border border-gray-300 p-3 focus:border-primary focus:outline-none transition-colors" placeholder="(555) 123-4567" />
              </div>
            </div>
            
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
               <div>
                <label className="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-2">Date</label>
                <input type="date" required className="w-full bg-white border border-gray-300 p-3 focus:border-primary focus:outline-none transition-colors" />
              </div>
              <div>
                <label className="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-2">Guests</label>
                <select className="w-full bg-white border border-gray-300 p-3 focus:border-primary focus:outline-none transition-colors">
                  <option>2 People</option>
                  <option>3 People</option>
                  <option>4 People</option>
                  <option>5+ People</option>
                </select>
              </div>
            </div>

            <div>
              <label className="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-2">Message</label>
              <textarea rows={4} className="w-full bg-white border border-gray-300 p-3 focus:border-primary focus:outline-none transition-colors" placeholder="Any dietary restrictions or special requests?"></textarea>
            </div>

            <button 
              type="submit" 
              disabled={formStatus !== 'idle'}
              className={`w-full py-4 text-white font-bold uppercase tracking-widest transition-all duration-300 ${
                formStatus === 'success' ? 'bg-green-600' : 'bg-primary hover:bg-red-800'
              }`}
            >
              {formStatus === 'idle' ? 'Request Reservation' : formStatus === 'submitting' ? 'Sending...' : 'Confirmed!'}
            </button>
          </form>
        </div>
      </div>

      {/* Footer */}
      <footer className="bg-black py-8 text-center text-gray-600 text-sm">
        <p>&copy; {new Date().getFullYear()} The Golden Lotus. All rights reserved.</p>
      </footer>
    </section>
  );
};

export default Contact;