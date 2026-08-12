import React, { useState, useEffect } from 'react';
import { Menu as MenuIcon, X, UtensilsCrossed } from 'lucide-react';

interface NavbarProps {
  bannerVisible?: boolean;
}

const Navbar: React.FC<NavbarProps> = ({ bannerVisible = false }) => {
  const [isOpen, setIsOpen] = useState(false);
  const [scrolled, setScrolled] = useState(false);

  useEffect(() => {
    const handleScroll = () => {
      setScrolled(window.scrollY > 50);
    };
    window.addEventListener('scroll', handleScroll);
    return () => window.removeEventListener('scroll', handleScroll);
  }, []);

  const navLinks = [
    { name: 'Home', href: '#hero' },
    { name: 'About', href: '#about' },
    { name: 'Menu', href: '#menu' },
    { name: 'Gallery', href: '#gallery' },
    { name: 'Contact', href: '#contact' },
  ];

  const toggleMenu = () => setIsOpen(!isOpen);

  const handleLinkClick = (e: React.MouseEvent<HTMLAnchorElement>, href: string) => {
    e.preventDefault();
    const targetId = href.replace('#', '');
    const element = document.getElementById(targetId);

    if (element) {
      const navHeight = scrolled ? 60 : 90;
      const bannerHeight = bannerVisible ? 48 : 0;
      const totalOffset = navHeight + bannerHeight + 20; 

      const elementPosition = element.getBoundingClientRect().top;
      const offsetPosition = elementPosition + window.scrollY - totalOffset;

      window.scrollTo({
        top: offsetPosition,
        behavior: "smooth"
      });
    }

    if (isOpen) {
      setIsOpen(false);
    }
  };

  return (
    <nav 
      className={`fixed w-full z-50 transition-all duration-300 ${
        scrolled ? 'bg-foreground/95 text-white shadow-lg py-2' : 'bg-transparent text-white py-6'
      } ${bannerVisible ? 'top-12' : 'top-0'}`}
    >
      {/* Added relative z-50 to ensure this container sits above the overlay */}
      <div className="container mx-auto px-4 md:px-8 flex justify-between items-center relative z-50">
        <a 
          href="#hero" 
          onClick={(e) => handleLinkClick(e, '#hero')}
          className="flex items-center gap-2 font-serif text-2xl font-bold tracking-wider text-secondary"
        >
          <UtensilsCrossed className="w-6 h-6" />
          <span>GOLDEN LOTUS</span>
        </a>

        {/* Desktop Menu */}
        <div className="hidden md:flex space-x-8">
          {navLinks.map((link) => (
            <a 
              key={link.name} 
              href={link.href} 
              onClick={(e) => handleLinkClick(e, link.href)}
              className="text-sm uppercase tracking-widest hover:text-secondary transition-colors cursor-pointer"
            >
              {link.name}
            </a>
          ))}
        </div>

        {/* Mobile Menu Button */}
        <button 
          onClick={toggleMenu} 
          className="md:hidden text-white focus:outline-none hover:text-secondary transition-colors"
          aria-label={isOpen ? "Close menu" : "Open menu"}
        >
          {isOpen ? <X className="w-8 h-8" /> : <MenuIcon className="w-8 h-8" />}
        </button>
      </div>

      {/* Mobile Overlay */}
      <div 
        className={`fixed inset-0 bg-foreground/95 z-40 flex flex-col items-center justify-center space-y-8 transition-transform duration-300 md:hidden ${
          isOpen ? 'translate-x-0' : 'translate-x-full'
        }`}
      >
        {navLinks.map((link) => (
          <a 
            key={link.name} 
            href={link.href} 
            onClick={(e) => handleLinkClick(e, link.href)}
            className="text-2xl font-serif text-white hover:text-secondary cursor-pointer"
          >
            {link.name}
          </a>
        ))}
      </div>
    </nav>
  );
};

export default Navbar;